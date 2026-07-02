<?php
declare(strict_types=1);
function e(string $value): string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function unquote(string $value): string{$value=trim($value);if($value==='')return'';if((str_starts_with($value,'"')&&str_ends_with($value,'"'))||(str_starts_with($value,"'")&&str_ends_with($value,"'")))return substr($value,1,-1);return $value;}
function is_https_url(string $value): bool{if($value===''||filter_var($value,FILTER_VALIDATE_URL)===false)return false;$parts=parse_url($value);return is_array($parts)&&isset($parts['scheme'])&&strtolower((string)$parts['scheme'])==='https';}
function is_safe_image_src(string $value): bool{if($value==='')return false;if(str_starts_with($value,'/'))return true;return is_https_url($value);}
$profileyaml=isset($profileyaml)&&is_string($profileyaml)?$profileyaml:'';
$profileName=isset($profileName)&&is_string($profileName)&&$profileName!==''?$profileName:'Profile';
$description=isset($description)&&is_string($description)?$description:'';
$profilePicture=isset($profilePicture)&&is_string($profilePicture)?$profilePicture:'';
$profileAlt=isset($profileAlt)&&is_string($profileAlt)?$profileAlt:'';
$links=[];
$lines=preg_split('/\R/',trim($profileyaml));
if(is_array($lines)){
foreach($lines as $line){
$line=trim((string)$line);
if($line===''||str_starts_with($line,'#'))continue;
if(!preg_match('/^([^:]+):\s*(.+)$/',$line,$match))continue;
$key=trim($match[1]);
$value=trim($match[2]);
if($key==='profilepicture'){
$scalar=unquote($value);
if(is_safe_image_src($scalar))$profilePicture=$scalar;
continue;
}
if($key==='profilepicturealt'){
$scalar=unquote($value);
if($scalar!=='')$profileAlt=$scalar;
continue;
}
if(preg_match('/^\[\s*(["\'])(.*?)\1\s*,\s*(["\'])(.*?)\3\s*\]$/',$value,$arrayMatch)){
$url=trim($arrayMatch[2]);
$icon=trim($arrayMatch[4]);
if(is_https_url($url)&&is_safe_image_src($icon)){
$links[]=['title'=>$key,'url'=>$url,'icon'=>$icon];
}
}
}
}
if($profileAlt==='')$profileAlt=$profileName;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="description" content="<?php echo e($description); ?>">
<title><?php echo e($profileName); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{font-family:Inter,Arial,Helvetica,sans-serif;background:#000;color:#fff;display:flex;justify-content:center;padding:32px 14px;font-weight:800}
.page{width:min(100%,580px);min-height:calc(100svh - 64px);display:flex;flex-direction:column;align-items:center}
.profile-picture{width:96px;height:96px;border-radius:50%;object-fit:cover;display:block;margin:34px auto 18px;background:#1a1a1a;border:1px solid #242424}
h1{font-size:20px;line-height:1.2;font-weight:800;text-align:center;margin-bottom:8px;letter-spacing:-0.01em}
.bio{color:#d0d0d0;text-align:center;font-size:14px;line-height:1.45;max-width:42ch;margin-bottom:26px;font-weight:700}
.links{width:100%;display:flex;flex-direction:column;gap:12px}
.link{width:100%;display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:16px;background:#111;color:#fff;text-decoration:none;border:1px solid #242424;transition:background .15s ease,border-color .15s ease,transform .15s ease}
.link:hover,.link:focus-visible{background:#181818;border-color:#343434;transform:translateY(-1px);outline:none}
.link-icon{width:28px;height:28px;object-fit:cover;border-radius:8px;display:block;flex:0 0 28px;background:#1a1a1a}
.link-text{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:15px;line-height:1.2}
.footer{margin-top:auto;padding-top:24px;color:#7a7a7a;font-size:12px;font-weight:700;text-align:center}
@media (max-width:480px){
body{padding:18px 10px}
.page{min-height:calc(100svh - 36px)}
.link{padding:13px 14px}
}
</style>
</head>
<body>
<main class="page">
<?php if($profilePicture!==''): ?>
<img class="profile-picture" src="<?php echo e($profilePicture); ?>" alt="<?php echo e($profileAlt); ?>">
<?php endif; ?>
<h1><?php echo e($profileName); ?></h1>
<?php if($description!==''): ?>
<p class="bio"><?php echo e($description); ?></p>
<?php endif; ?>
<?php if($links!==[]): ?>
<nav class="links" aria-label="Profil-Links">
<?php foreach($links as $link): ?>
<a class="link" href="<?php echo e((string)$link['url']); ?>" target="_blank" rel="noopener noreferrer external nofollow">
<?php if(isset($link['icon'])&&is_string($link['icon'])&&$link['icon']!==''): ?>
<img class="link-icon" src="<?php echo e($link['icon']); ?>" alt="" aria-hidden="true">
<?php endif; ?>
<span class="link-text"><?php echo e((string)$link['title']); ?></span>
</a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<div class="footer"><?php echo e($profileName); ?></div>
</main>
</body>
</html>
