OVERVIEW

'Allocator' is a small, self-contained PHP application that emulates an inventory processing 
and maintenance system.  Data, including current inventory and incoming allocation requests,
are stored and processed as JSON files.  The inventory file--inventory.json--is updated after
every valid transaction is processed.  The log file--processed.json--maintains a log of every
successful transaction.

Since CLI PHP is single-threaded, 'streams' are emulated by order request files in a subdirectory
named 'Streams.'   A stream is identified by its filename; e.g. the file 'Stream1' identifies 
stream 'Stream1,' 'Stream2' identifies stream 'Stream2' and so forth.  Additonal streams may
be introduced for processing by creating an appropriate file--in valid JSON--in the Streams
folder, which will be processed in the next run of Allocator.php.

While each successful order processed will be recorded in the 'processed' log file at the 
completion of the transaction, HTML output will be only be displayed at the completion of all
orders and streams.

NOTE: This application was developed as a CLI app in Zend Studio.  The HTML output is displayed
in the 'Browser Output' window; if your IDE doesn't have this feature the HTML output will be 
available in the file 'processed.html' at the end of the processing run.  Popular IDEs--Zend Studio,
Eclipse, phpStorm and similar--should be able to run the app in 'CLI Mode,' with or without 
debugging.


DEV ENVIRONMENT

PHP/Zend CLI 5.5.7


FILES

Allocator.php       - PHP CLI mainline with supporting classes.
inventory.json      - Existing inventory as JSON
inventory_high.json - A copy of 'max' inventory to restore modified inventory.json for subsequent runs
inventory_low.json  - A copy of 'min' inventory to restore modified inventory.json for subsequent runs
processed.json      - Processing result of entire run as JSON (created if non-existent)
processed.html      - Processing result of entire run as HTML (created if non-existent)
lock.txt            - Plain text file only used as locking mechanism in stream emulation
README.TXT          - This file
Streams (folder)    - Folder containing individual 'streams' as incoming orders in JSON; Stream1 and Stream3
                      represent the sample input in the challenge.
 

INSTALLATION

All files and folders are relative to whichever folder the app is installed in.  Download from GitHub
to a local folder of your choosing (C:\ recommended).


RUNNING

In your IDE, navigate to the folder the app is installed in and open Allocator.php.  Run the PHP file
in the fashion appropriate for the IDE.


OUTPUT

Fatal errors are caught and cause an interruptible exception.  Streams and orders are processed until
all orders have been processed, or inventory empties, whichever comes first.  A record of all processed
transactions is maintained in JSON in processed.json.  The accumulated HTML output--a table for each 
processed stream--will be in processed.html.  Load processed.html in a browser window/tab to view the 
HTML-formatted results.