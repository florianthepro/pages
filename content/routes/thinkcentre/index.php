<?php declare(strict_types=1);

# Geteilte Instanz-Variablen defensiv einlesen (Muster wie launcher-v2).
$thinkcentre_theme=(isset($thinkcentre_theme)&&in_array($thinkcentre_theme,['light','dark'],true))?$thinkcentre_theme:'auto';
$thinkcentre_title=(isset($thinkcentre_title)&&$thinkcentre_title!=='')?(string)$thinkcentre_title:'Homelab Projektübersicht – ThinkCentre';
$thinkcentre_icon=(isset($thinkcentre_icon)&&is_string($thinkcentre_icon)&&$thinkcentre_icon!=='')?$thinkcentre_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/thinkcentre/index.svg';

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

# Rein statische Inhaltsseite (Homelab-Projektverzeichnis). Kein Backend noetig -
# der Text stammt 1:1 aus dem Original. 'desc'/'anleitung' sind bewusst als
# fertiges HTML hinterlegt (enthalten <br> bzw. &amp;) und werden unescaped
# ausgegeben; alle uebrigen Felder laufen defensiv durch h().
$thinkcentre_projects=[
	[
		'h2'=>'Docker / Portainer',
		'meta'=>'Kategorie: Container | Zeitaufwand: 1–2h',
		'desc'=>'Plattform zum Hosten von Containern (UptimeKuma, Vaultwarden, Pi‑hole usw.).',
		'materials'=>['https://www.portainer.io','https://www.docker.com'],
		'anleitung'=>'Linux installieren → Docker installieren → Portainer Container starten → Dienste hinzufügen.',
		'img'=>'https://www.docker.com/app/uploads/2024/02/cropped-docker-logo-favicon-270x270.png',
	],
	[
		'h2'=>'Proxmox Virtualisierung',
		'meta'=>'Kategorie: Virtualisierung | Zeitaufwand: 1–2h',
		'desc'=>'Hypervisor zum Betreiben von VMs und LXC-Containern.',
		'materials'=>['https://www.proxmox.com'],
		'anleitung'=>'ISO laden → Installation → Storage definieren → VMs anlegen.',
		'img'=>'https://www.pngrepo.com/download/331552/proxmox.png',
	],
	[
		'h2'=>'TrueNAS Scale',
		'meta'=>'Kategorie: Storage / NAS | Zeitaufwand: 1–2h',
		'desc'=>'ZFS‑basiertes NAS mit SMB/NFS und Docker/Katalog-Apps.',
		'materials'=>['https://www.truenas.com'],
		'anleitung'=>'Bootmedium erstellen → Installation → Pool anlegen → Freigaben definieren.',
		'img'=>'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/png/truenas.png',
	],
	[
		'h2'=>'Jellyfin / Plex Media Server',
		'meta'=>'Kategorie: Medien / Streaming | Zeitaufwand: 1–3h',
		'desc'=>'Selbstgehostetes Streaming für Videos und Musik.',
		'materials'=>['https://jellyfin.org'],
		'anleitung'=>'Server starten → Medienordner einbinden → Clients verbinden.',
		'img'=>'https://static0.xdaimages.com/wordpress/wp-content/uploads/2024/02/jellyfin-logo.png?q=70&fit=contain&w=420&dpr=1',
	],
	[
		'h2'=>'Minecraft Server (Java + Bedrock)',
		'meta'=>'Kategorie: Gaming | Zeitaufwand: 1–4h',
		'desc'=>'Stabiler Gameserver für Java &amp; Bedrock parallel.',
		'materials'=>['https://www.minecraft.net','https://mineos.net/','https://www.alpinelinux.org/','https://ubuntu.com/'],
		'anleitung'=>'Java installieren auf Ubuntu Server (LTS, ohne GUI) oder in Docker auf Alpin Linux (x86_64).<br>→ Server‑Jar starten → Ports freigeben.',
		'img'=>'https://www.freeiconspng.com/uploads/minecraft-icon-5.png',
	],
	[
		'h2'=>'Valheim Dedicated Server',
		'meta'=>'Kategorie: Gaming | Zeitaufwand: 1–3h',
		'desc'=>'Koop‑Survival‑Server, sehr ressourcenschonend.',
		'materials'=>['https://valheim-server.de'],
		'anleitung'=>'SteamCMD → Server herunterladen → Startscript anpassen.',
		'img'=>'https://tse1.mm.bing.net/th/id/OIP.Xkg6K5XxdMHW2dddptBeZAHaDQ?rs=1&pid=ImgDetMain&o=7&rm=3',
	],
	[
		'h2'=>'Factorio Server',
		'meta'=>'Kategorie: Gaming | Zeitaufwand: 30–90 min',
		'desc'=>'Extrem effizienter Automations‑Game‑Server.',
		'materials'=>['https://factorio.com'],
		'anleitung'=>'Archiv laden → config.ini anpassen → Ports öffnen.',
		'img'=>'https://commands.gg/images/cta-boxes/factorio.png',
	],
	[
		'h2'=>'Home Assistant',
		'meta'=>'Kategorie: Smart Home | Zeitaufwand: 1–2h',
		'desc'=>'Zentrale Smart‑Home‑Steuerung mit Integrationen &amp; Add‑ons.',
		'materials'=>['https://www.home-assistant.io'],
		'anleitung'=>'Installation → Integrationen verbinden → Automationen erstellen.',
		'img'=>'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/png/home-assistant.png',
	],
	[
		'h2'=>'Pi-hole / AdGuard Home',
		'meta'=>'Kategorie: Netzwerk / Sicherheit | Zeitaufwand: 30–60 min',
		'desc'=>'Werbe‑ und Tracking‑Blocker für das gesamte Netzwerk.',
		'materials'=>['https://pi-hole.net'],
		'anleitung'=>'DNS ändern → Installation → Blocklisten aktivieren.',
		'img'=>'https://i.pinimg.com/originals/4d/a0/cc/4da0cc5c02df7ab1f8188ba01444dc8e.png',
	],
	[
		'h2'=>'WireGuard VPN',
		'meta'=>'Kategorie: Netzwerk / VPN | Zeitaufwand: 30–90 min',
		'desc'=>'Schnelles, modernes VPN für sicheren Remote‑Zugriff.',
		'materials'=>['https://www.wireguard.com'],
		'anleitung'=>'Keys erzeugen → wg.conf anpassen → Ports freigeben → Clients einrichten.',
		'img'=>'https://www.scalefactory.com/blog/2020/12/16/wireguard-vpn-for-remote-working/img/wireguard.png',
	],
];

# data-theme nur bei fixem Theme setzen, bei 'auto' weglassen (folgt System).
$dt=$thinkcentre_theme==='light'?' data-theme="light"':($thinkcentre_theme==='dark'?' data-theme="dark"':'');
?>
<!DOCTYPE html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="UTF-8">
<title><?= h($thinkcentre_title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="<?= h($thinkcentre_icon) ?>">
<link rel="apple-touch-icon" href="<?= h($thinkcentre_icon) ?>">
<style>
/* Originaldesign ist dunkel; hier per Variablen theme-faehig gemacht
   (hell = Default, dunkel = Original, Auto folgt dem System). */
:root{
	--bg:#f2f2f2;--fg:#1f1f1f;
	--header-bg:#111;--header-fg:#fff;
	--card-bg:#ffffff;--card-accent:#999;
	--title:#111;--meta:#666;
	--img-border:#ddd;--link:#0a63c9;--footer:#888;
}
@media (prefers-color-scheme:dark){
	:root:not([data-theme="light"]){
		--bg:#1a1a1a;--fg:#e0e0e0;
		--header-bg:#000;--header-fg:#fff;
		--card-bg:#2b2b2b;--card-accent:#666;
		--title:#fff;--meta:#aaa;
		--img-border:#444;--link:#4da3ff;--footer:#777;
	}
}
:root[data-theme="dark"]{
	--bg:#1a1a1a;--fg:#e0e0e0;
	--header-bg:#000;--header-fg:#fff;
	--card-bg:#2b2b2b;--card-accent:#666;
	--title:#fff;--meta:#aaa;
	--img-border:#444;--link:#4da3ff;--footer:#777;
}
body{
	font-family: Consolas, "Courier New", monospace;
	background-color: var(--bg);
	margin: 0;
	padding: 0;
	color: var(--fg);
}
header{
	padding: 20px 30px;
	background: var(--header-bg);
	color: var(--header-fg);
}
h1{
	margin: 0;
	font-size: 26px;
}
main{
	padding: 20px 30px;
	max-width: 1200px;
	margin: auto;
}
.project{
	background: var(--card-bg);
	padding: 20px;
	margin-bottom: 18px;
	border-left: 4px solid var(--card-accent);
	border-radius: 4px;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}
.project h2{
	margin-top: 0;
	font-size: 20px;
	color: var(--title);
}
.meta{
	color: var(--meta);
	font-size: 14px;
	margin-bottom: 10px;
}
.project img{
	max-width: 260px;
	border: 1px solid var(--img-border);
	margin-left: 20px;
}
a{
	color: var(--link);
	text-decoration: none;
}
a:hover{
	text-decoration: underline;
}
footer{
	text-align: center;
	padding: 25px;
	font-size: 14px;
	color: var(--footer);
	margin-top: 30px;
}
.textblock{
	flex: 1;
}
@media (max-width:640px){
	.project{flex-direction:column}
	.project img{margin-left:0;margin-top:16px;max-width:100%}
}
</style>
</head>
<body>
<header>
<h1>Homelab Projektübersicht – Lenovo ThinkCentre</h1>
</header>
<main>
<?php foreach($thinkcentre_projects as $p): ?>
<div class="project">
<div class="textblock">
<h2><?= h($p['h2']) ?></h2>
<div class="meta"><?= h($p['meta']) ?></div>
<p><strong>Beschreibung:</strong><br><?= $p['desc'] ?></p>
<p><strong>Externe Materialien:</strong><br><?php $mParts=[]; foreach($p['materials'] as $u){$mParts[]='<a href="'.h($u).'" target="_blank" rel="noopener noreferrer">'.h($u).'</a>';} echo implode('<br>',$mParts); ?></p>
<p><strong>Grobe Anleitung:</strong><br><?= $p['anleitung'] ?></p>
</div><img src="<?= h($p['img']) ?>" alt="" loading="lazy"></div>
<?php endforeach; ?>
</main>
<footer>© 2026 – Homelab Projektverzeichnis / M93p ThinkCentre 10AA</footer>
</body>
</html>
