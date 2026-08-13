<?php
if(!empty($content)){
header('Content-Type:text/html; charset=UTF-8');
echo base64_decode($content);
exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Base64 Generator</title>
<style>
body{font-family:Arial,sans-serif;max-width:1000px;margin:20px auto;padding:20px}
textarea{width:100%;height:300px}
input{width:100%;margin-top:10px}
button{margin-top:10px;padding:8px 16px}
</style>
</head>
<body>
<textarea id="html"></textarea>
<button onclick="generate()">Base64 erzeugen</button>
<input id="out" readonly>
<script>
function generate(){
out.value=btoa(unescape(encodeURIComponent(html.value)));
}
</script>
</body>
</html>
