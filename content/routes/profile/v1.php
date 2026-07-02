<?php
foreach (preg_split('/\R/', trim($yaml)) as $line) {
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
function e(string $value): string {
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
            opacity: .96;
            font-size: 16px;
            line-height: 1.45;
            font-weight: 800;
            text-align: center;
            margin-bottom: 30px;
        }
        .links {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .link {
            width: 100%;
            min-height: 64px;
            border-radius: 16px;
            background: #151515;
            color: #fff;
            text-decoration: none;
            display: grid;
            grid-template-columns: 44px 1fr 44px;
            align-items: center;
            padding: 10px 14px;
            transition: transform .14s ease, background .14s ease;
        }
        .link:hover {
            background: #202020;
            transform: translateY(-1px);
        }
        .link:active {
            transform: scale(.99);
        }
        .link:focus-visible {
            outline: 2px solid #fff;
            outline-offset: 4px;
        }
        .icon {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            display: block;
        }
        .label {
            min-width: 0;
            text-align: center;
            font-size: 16px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (max-width: 420px) {
            body {
                padding: 24px 14px;
            }
            .page {
                min-height: calc(100svh - 48px);
            }
            .profile-picture {
                margin-top: 26px;
            }
            .links {
                gap: 14px;
            }
        }
</style>
</head>
<body>
<main class="page">
<?php if ($profilePicture !== ''): ?>
<img class="profile-picture" src="<?= e($profilePicture) ?>" alt="<?= e($profileAlt) ?>">
<?php endif; ?>
<h1><?= e($profileName) ?></h1>
<p class="bio"><?= e($description) ?></p>
<nav class="links" aria-label="Links">
<?php foreach ($links as $link): ?>
<a class="link" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer">
<span class="icon">
<img src="<?= e($link['icon']) ?>" alt="" loading="lazy">
</span>
<span class="label"><?= e($link['title']) ?></span>
</a>
<?php endforeach; ?>
</nav>
</main>
</body>
</html>
