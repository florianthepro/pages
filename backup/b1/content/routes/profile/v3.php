<?php
foreach (preg_split('/\R/', trim($profileyaml)) as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (!preg_match('/^([^:]+):\s*(.+)$/', $line, $match)) {
        continue;
    }

    $title = trim($match[1]);
    $value = trim($match[2]);

    if (preg_match('/^\[\s*(["\'])(.*?)\1\s*,\s*(["\'])(.*?)\3\s*\]$/', $value, $arrayMatch)) {
        $links[] = [
            'title' => $title,
            'url' => $arrayMatch[2],
            'icon' => $arrayMatch[4],
        ];
        continue;
    }

    if ($profilePicture === '') {
        $profileAlt = $title;
        $profilePicture = trim($value, " \t\n\r\0\x0B\"'");
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($description) ?>">
    <title><?= e($profileName) ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            font-family: Inter, Arial, Helvetica, sans-serif;
            background: #000;
            color: #fff;
            display: flex;
            justify-content: center;
            padding: 32px 14px;
            font-weight: 800;
        }

        .page {
            width: min(100%, 580px);
            min-height: calc(100svh - 64px);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-picture {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 34px auto 18px;
            background: #1a1a1a;
        }

        h1 {
            font-size: 20px;
            line-height: 1.2;
            font-weight: 800;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .bio {
            color: #fff;
