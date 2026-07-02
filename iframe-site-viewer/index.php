<?php
#$side = "https://example.com";

if (!filter_var($side, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit("Ungültige URL in \$side.");
}

$parts = parse_url($side);
if (!isset($parts["scheme"]) || !in_array(strtolower($parts["scheme"]), ["http", "https"], true)) {
    http_response_code(400);
    exit("Nur HTTP/HTTPS URLs sind erlaubt.");
}

function absolutUrl(string $url, string $base): string
{
    if ($url === "") return "";
    if (preg_match("~^https?://~i", $url)) return $url;
    if (str_starts_with($url, "//")) {
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: "https";
        return $scheme . ":" . $url;
    }

    $baseParts = parse_url($base);
    $scheme = $baseParts["scheme"] ?? "https";
    $host = $baseParts["host"] ?? "";
    $port = isset($baseParts["port"]) ? ":" . $baseParts["port"] : "";

    if (str_starts_with($url, "/")) {
        return $scheme . "://" . $host . $port . $url;
    }

    $path = $baseParts["path"] ?? "/";
    $dir = preg_replace("~/[^/]*$~", "/", $path);
    return $scheme . "://" . $host . $port . $dir . $url;
}

function ladeMetaDaten(string $url): array
{
    $title = parse_url($url, PHP_URL_HOST) ?: "Webseite";
    $icon = "";

    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "timeout" => 6,
            "header" => "User-Agent: Mozilla/5.0 (compatible; PHP Mirror)\r\n"
        ],
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true
        ]
    ]);

    $html = @file_get_contents($url, false, $context, 0, 300000);
    if ($html === false || trim($html) === "") {
        return [$title, $icon];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    libxml_clear_errors();

    $titleTags = $dom->getElementsByTagName("title");
    if ($titleTags->length > 0) {
        $foundTitle = trim($titleTags->item(0)->textContent);
        if ($foundTitle !== "") $title = $foundTitle;
    }

    $links = $dom->getElementsByTagName("link");
    foreach ($links as $link) {
        $rel = strtolower($link->getAttribute("rel"));
        if (str_contains($rel, "icon")) {
            $href = trim($link->getAttribute("href"));
            if ($href !== "") {
                $icon = absolutUrl($href, $url);
                break;
            }
        }
    }

    if ($icon === "") {
        $icon = (parse_url($url, PHP_URL_SCHEME) ?: "https") . "://" . (parse_url($url, PHP_URL_HOST) ?: "") . "/favicon.ico";
    }

    return [$title, $icon];
}

[$titel, $icon] = ladeMetaDaten($side);
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titel, ENT_QUOTES, "UTF-8") ?></title>
    <?php if ($icon !== ""): ?>
        <link rel="icon" href="<?= htmlspecialchars($icon, ENT_QUOTES, "UTF-8") ?>">
        <link rel="shortcut icon" href="<?= htmlspecialchars($icon, ENT_QUOTES, "UTF-8") ?>">
    <?php endif; ?>
    <style>
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #fff;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }
    </style>
</head>
<body>
    <iframe src="<?= htmlspecialchars($side, ENT_QUOTES, "UTF-8") ?>" referrerpolicy="no-referrer-when-downgrade"></iframe>
</body>
</html>
