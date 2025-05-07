<?php
require 'vendor/autoload.php'; // Load Composer autoloader

$pdf = new TCPDF(); // Create new PDF object
$pdf->AddPage();    // Add a page
$pdf->Write(10, 'Hello World!'); // Write text
$pdf->Output('hello_world.pdf', 'I'); // Output to browser
?>