<?php

/**
 * Simulate simultaneous 'streams' by getting files of orders (streams) from a folder
 */
class getStreams
{
    protected $_path = null;
    
    function __construct($path) {
        $this->_path = $path;
    }
    
    /**
     * Get list of 'streams'
     */
    function getFnames() {
        $dir = new DirectoryIterator($this->_path);
        $fnames = array();
        foreach($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                $fnames[] = $fileinfo->getBasename();
            }
        }
        // need at least one stream
        if (empty($fnames)) {
            throw new Exception('No streams.');
        }
        return $fnames;
    }
}

/**
 * Process each order from a stream.
 */
class orderAllocator
{
    const INVENTORY_FNAME = 'inventory.json';
    const PROCESSED_FNAME = 'processed.json';
    const LOCK_FNAME = 'lock.txt';
    const MIN_QTY = 0;
    const MAX_QTY = 5;
    
    protected $_order = null;
    protected $_inventory_handle = null;
    protected $_processed_handle = null;
    protected $_lockfile_handle = null;
    protected $_inventory = null;
    protected $_stream = null;
    protected $_output = null;
    protected $_html_output = '';
    protected $_timestamp;
    protected $_buckets = array('Ordered', 'Filled', 'Backordered'); 

	function __construct($stream) {
	    $this->_timestamp = time();
	    $this->_stream = $stream;
	    // lock the lock file to block other 'streams'
	    $this->_lockfile_handle = fopen(self::LOCK_FNAME, 'w');
	    flock($this->_lockfile_handle, LOCK_EX);    
	    // get current inventory and close file (we'll write it back later)
		$this->_inventory_handle = fopen(self::INVENTORY_FNAME, 'r+');
		$this->_inventory = json_decode(fread($this->_inventory_handle, filesize(self::INVENTORY_FNAME)), true);
		// verify valid inventory JSON
		if (is_null($this->_inventory)) {
		    throw new Exception('Invalid or nonexistent inventory file.');
		}
		if (0 == $this->countInventory()) {
		    throw new Exception('No inventory.');
		}
		// open the 'processed' file to record processed orders
        $this->_processed_handle = fopen(self::PROCESSED_FNAME, 'a');
	}
	
	/**
	 * Calc total inventory.
	 */
	function countInventory() {
	    $cnt = 0;
	    foreach($this->_inventory as $available) {
	        $cnt += $available;
	    }
	    return $cnt;
	}
	
	/** 
	 * Find a product's line item in an order.
	 * @return Found line item; false if not found.
	 */
	private function getLine($order, $product) {
	    foreach($order['Lines'] as $line) {
	        if ($product == $line['Product']) {
	            return $line;
	        }
	    }
	    return false;
	}

	/**
	 * Process every line of each order within a stream (unless error or inventory gone).
	 */
	function processOrder($order) {
	    if (0 == $this->countInventory()) {
	        return false;
	    }
	    $this->_order = $order;  
	    foreach($this->_inventory as $product=>$quantity) {
	        // prefill output grid with zeroes
	        foreach($this->_buckets as $bucket) {
	            $this->_output[$this->_order['Header']][$bucket][$product] = '0';
	        }
	        // see if it's one of the lines in our order
	        $line = $this->getLine($order, $product);
            if (false !== $line) {
                $product = $line['Product'];
    	        $quantity = $line['Quantity'];
    	        // verify valid product and quantity for each line
    	        if (!isset($this->_inventory[$product])) {
    	            throw new Exception('Invalid order: Product ' . "'" . $product . "' not in inventory.");
    	        }
    	        if (($quantity < self::MIN_QTY) || ($quantity > self::MAX_QTY)) {
    	            throw new Exception('Invalid order: Product ' . "'" . $product . "', Quantity " . $quantity . 
    	                   ' not a valid quantity.');
    	        }
                // find the right bucket(s)
                $this->_output[$this->_order['Header']]['Ordered'][$product] = (string)$quantity;
                if ($this->_inventory[$product] < $quantity) {
                    // backordered
                    $this->_output[$this->_order['Header']]['Backordered'][$product] = (string)$quantity;
                }
                else {
                    // filled
                    $this->_inventory[$product] = (string)($this->_inventory[$product] - $quantity);
                    $this->_output[$this->_order['Header']]['Filled'][$product] = (string)$quantity;
                }              
    	    }
	    }
    }
    
    /**
     * Accumulate results as HTML, record order processing and update inventory file.
     */
    function recordOrder() {
        // first, record processing as JSON in file
        foreach($this->_output as $header=>$buckets) {
            $processed = '{"Timestamp":' . '"' . date('Y-m-d H:i:s', $this->_timestamp) . '",' .
                    '"Stream":' . '"' . $this->_stream . '",' . '"Header":' . '"' . $header . '",';
            $json = json_encode($buckets);
            // have to remove the 'extra' { and }
            $json = substr($json, 1, (strlen($json) - 1));
            $processed .= $json . "\n";
            fwrite($this->_processed_handle, $processed, strlen($processed));
        }
        fclose($this->_processed_handle);
        // update the inventory file
        ftruncate($this->_inventory_handle, 0);
        rewind($this->_inventory_handle);
        fwrite($this->_inventory_handle, json_encode($this->_inventory));
        fclose($this->_inventory_handle);
        // unlock the lock file
        flock($this->_lockfile_handle, LOCK_UN);
        fclose($this->_lockfile_handle); 
        // second, record processed as HTML
        $items = count($this->_inventory);
        $itemsRow = '';
        foreach($this->_inventory as $key=>$val) {
            $itemsRow .= '<td>' . $key . '</td>';
        }
        $this->_html_output .= <<<OUT
<!doctype html>
<html>
	<style>
	table {
		border: 1px solid black;
	}
	</style>
	<body>
	   <span><strong>$this->_stream</strong></span><br>
		<table>
			<tr>
				<th>Header</th>
				<th colspan="$items">Ordered</th>
				<th colspan="$items">Filled</th>
				<th colspan="$items">Backordered</th>
			</tr>
            <tr>
                <td></td>
                $itemsRow$itemsRow$itemsRow 
            </tr>
OUT;
               
        $headers = array_keys($this->_output);
        for($i=0; $i<count($headers); $i++) {
            $this->_html_output .= '<tr><td>' . $headers[$i] . '</td>';
            foreach($this->_buckets as $bucket) {
                foreach($this->_output[$headers[$i]][$bucket] as $key=>$val) {
                    $this->_html_output .= '<td>' . $val . '</td>';
                }
            }
        }
        // stack up the HTML output
        $this->_html_output .= '</tr></table><br><br></body></html>';
        return $this->_html_output;
    }
}

/**
 * Display and save final output as HTML
 */
function postOutput($output){
    if ('' == $output) {
        echo 'No inventory.';
        return;
    }
    $out_handle = fopen(HTML_FNAME, 'w');
    fwrite($out_handle, $output);
    fclose($out_handle);
    echo $output;
}

/**
 * CLI mainline.
 */
const STREAMS_DNAME = 'Streams';
const HTML_FNAME = 'processed.html';

try {
    $allStreams = new getStreams(STREAMS_DNAME);
    $streams = $allStreams->getFnames();
    $output = '';
    foreach($streams as $stream) {
        $allocator = new orderAllocator($stream);
        $orders = json_decode(file_get_contents(STREAMS_DNAME . '\\' . $stream), true);
        foreach($orders as $order) {
    	    $inv = $allocator->processOrder($order);
    	    if (false === $inv) {
    	        // out of inventory
                break;
    	    }
        }
        $output .= $allocator->recordOrder();
    }
    postOutput($output);    
} catch (Exception $e) {
    die('Allocation failed with error: ' . $e->getMessage());
}
echo 'End of program.';