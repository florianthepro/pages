<?php
header('Content-Type:text/html;charset=UTF-8');
echo base64_decode($_GET['content'] ?? '');
