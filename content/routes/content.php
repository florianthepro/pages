<?php
if(isset($_GET['']) && $_GET['']!==''){
header('Content-Type:text/html;charset=UTF-8');
echo base64_decode($_GET['c']);
exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HTML → Base64 URL</title>
<style>
body{font-family:Arial,sans-serif;max-width:1000px;margin:20px auto;padding:20px}
textarea{width:100%;height:300px}
input{width:100%;margin-top:10px}
button{margin-top:10px;padding:8px 16px}
</style>
</head>
<body>
<textarea id="html" placeholder="HTML eingeben"></textarea>
<button onclick="generate()">URL erzeugen</button>
<input id="url" readonly>
<script>
function generate(){
const html=document.getElementById('html').value;
const b64=btoa(unescape(encodeURIComponent(html)));
document.getElementById('url').value=location.origin+location.pathname+'?='+encodeURIComponent(b64);
}
</script>
</body>
</html>
