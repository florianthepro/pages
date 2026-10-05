<?php
declare(strict_types=1);
$demo=[
'vlanGroups'=>[
'203.0.113.0/24'=>'WAN / Provider',
'10.0.1.0/24'=>'DMZ 10.0.1.0/24',
'10.0.2.0/24'=>'Server 10.0.2.0/24',
'10.0.4.0/24'=>'Storage 10.0.4.0/24',
'10.0.10.0/24'=>'Clients 10.0.10.0/24',
'10.0.20.0/24'=>'WLAN 10.0.20.0/24',
'10.0.99.0/24'=>'Mgmt 10.0.99.0/24'
],
'groupStyles'=>[
'WAN / Provider'=>['fill'=>'#f8fafc','stroke'=>'#64748b'],
'DMZ 10.0.1.0/24'=>['fill'=>'#fff7ed','stroke'=>'#ea580c'],
'Server 10.0.2.0/24'=>['fill'=>'#f0fdf4','stroke'=>'#15803d'],
'Storage 10.0.4.0/24'=>['fill'=>'#fefce8','stroke'=>'#a16207'],
'Clients 10.0.10.0/24'=>['fill'=>'#faf5ff','stroke'=>'#7c3aed'],
'WLAN 10.0.20.0/24'=>['fill'=>'#eff6ff','stroke'=>'#2563eb'],
'Mgmt 10.0.99.0/24'=>['fill'=>'#f8fafc','stroke'=>'#334155']
],
'devices'=>[
['Hostname'=>'inet','IP'=>'203.0.113.1','Type'=>'Internet/Provider','Kind'=>'Extern','Notes'=>'Glasfaser 1G','Connections'=>[]],
['Hostname'=>'fw01','IP'=>'10.0.1.1','Type'=>'Firewall','Kind'=>'Physisch','FwPolicy'=>'block','FwAllow'=>[443,53,123],'Notes'=>'Perimeter, block all + Freigaben','Connections'=>[
['connType'=>'Kabel','target'=>'inet','targetType'=>'hostname'],
['connType'=>'Kabel','target'=>'core01','targetType'=>'hostname','vlans'=>'1,2,10,20,99'],
['connType'=>'BGP','target'=>'inet','targetType'=>'hostname']
]],
['Hostname'=>'vpn01','IP'=>'10.0.1.2','Type'=>'VPN-Gateway','Kind'=>'Physisch','Connections'=>[
['connType'=>'Kabel','target'=>'fw01','targetType'=>'hostname'],
['connType'=>'IPsec-VPN','target'=>'inet','targetType'=>'hostname']
]],
['Hostname'=>'proxy01','IP'=>'10.0.1.3','Type'=>'Proxy','Kind'=>'VM','Connections'=>[
['connType'=>'HTTP-Proxy','target'=>'fw01','targetType'=>'hostname','port'=>8080]
]],
['Hostname'=>'web01','IP'=>'10.0.1.10','Type'=>'Web-Server','Kind'=>'VM','Connections'=>[
['connType'=>'MSSQL','target'=>'db01','targetType'=>'hostname']
]],
['Hostname'=>'core01','IP'=>'10.0.99.1','Type'=>'Switch','Kind'=>'Physisch','Notes'=>'Core-Switch','Connections'=>[
['connType'=>'Kabel','target'=>'sw-srv01','targetType'=>'hostname','vlans'=>'2'],
['connType'=>'Kabel','target'=>'sw-cl01','targetType'=>'hostname','vlans'=>'10'],
['connType'=>'Kabel','target'=>'sw-stor01','targetType'=>'hostname','vlans'=>'4'],
['connType'=>'Kabel','target'=>'ap01','targetType'=>'hostname','vlans'=>'20'],
['connType'=>'Kabel','target'=>'ap02','targetType'=>'hostname','vlans'=>'20']
]],
['Hostname'=>'sw-srv01','IP'=>'10.0.99.2','Type'=>'Switch','Kind'=>'Physisch','Connections'=>[
['connType'=>'Kabel','target'=>'esx01','targetType'=>'hostname','vlans'=>'2'],
['connType'=>'Kabel','target'=>'esx02','targetType'=>'hostname','vlans'=>'2']
]],
['Hostname'=>'sw-cl01','IP'=>'10.0.99.3','Type'=>'Switch','Kind'=>'Physisch','Connections'=>[
['connType'=>'Kabel','target'=>'pc-buero1','targetType'=>'hostname','vlans'=>'10'],
['connType'=>'Kabel','target'=>'pc-buero2','targetType'=>'hostname','vlans'=>'10'],
['connType'=>'Kabel','target'=>'pc-empfang','targetType'=>'hostname','vlans'=>'10'],
['connType'=>'Kabel','target'=>'prn-eg','targetType'=>'hostname','vlans'=>'10'],
['connType'=>'Kabel','target'=>'cam-hof','targetType'=>'hostname','vlans'=>'10']
]],
['Hostname'=>'sw-stor01','IP'=>'10.0.99.4','Type'=>'Switch','Kind'=>'Physisch','Connections'=>[
['connType'=>'Kabel','target'=>'nas01','targetType'=>'hostname','vlans'=>'4'],
['connType'=>'Kabel','target'=>'nas02','targetType'=>'hostname','vlans'=>'4']
]],
['Hostname'=>'ap01','IP'=>'10.0.20.2','Type'=>'Access Point','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'ap02','IP'=>'10.0.20.3','Type'=>'Access Point','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'usv01','IP'=>'10.0.99.9','Type'=>'USV','Kind'=>'Physisch','Connections'=>[
['connType'=>'SNMP','target'=>'mon01','targetType'=>'hostname']
]],
['Hostname'=>'esx01','IP'=>'10.0.99.11','Type'=>'Hypervisor','Kind'=>'Physisch','IPRange'=>'10.0.2.0/27','Notes'=>'Cluster-Host 1','Connections'=>[
['connType'=>'iSCSI','target'=>'nas01','targetType'=>'hostname','port'=>3260]
]],
['Hostname'=>'esx02','IP'=>'10.0.99.12','Type'=>'Hypervisor','Kind'=>'Physisch','IPRange'=>'10.0.2.32/27','Notes'=>'Cluster-Host 2','Connections'=>[
['connType'=>'iSCSI','target'=>'nas01','targetType'=>'hostname','port'=>3260]
]],
['Hostname'=>'dc01','IP'=>'10.0.2.10','Type'=>'Domain-Controller','Kind'=>'VM','Connections'=>[
['connType'=>'DNS','target'=>'dns01','targetType'=>'hostname']
]],
['Hostname'=>'dc02','IP'=>'10.0.2.40','Type'=>'Domain-Controller','Kind'=>'VM','Connections'=>[
['connType'=>'LDAP','target'=>'dc01','targetType'=>'hostname']
]],
['Hostname'=>'dns01','IP'=>'10.0.2.11','Type'=>'DNS-Server','Kind'=>'VM','Connections'=>[]],
['Hostname'=>'dhcp01','IP'=>'10.0.2.12','Type'=>'DHCP-Server','Kind'=>'VM','Connections'=>[]],
['Hostname'=>'mail01','IP'=>'10.0.2.13','Type'=>'Mail-Server','Kind'=>'VM','Connections'=>[
['connType'=>'SMTP','target'=>'inet','targetType'=>'hostname'],
['connType'=>'LDAP','target'=>'dc01','targetType'=>'hostname']
]],
['Hostname'=>'db01','IP'=>'10.0.2.14','Type'=>'Datenbank','Kind'=>'VM','Connections'=>[]],
['Hostname'=>'app01','IP'=>'10.0.2.15','Type'=>'Windows-Server','Kind'=>'VM','Connections'=>[
['connType'=>'MSSQL','target'=>'db01','targetType'=>'hostname'],
['connType'=>'SSH','target'=>'lnx01','targetType'=>'hostname']
]],
['Hostname'=>'lnx01','IP'=>'10.0.2.16','Type'=>'Linux-Server','Kind'=>'VM','Connections'=>[]],
['Hostname'=>'rds01','IP'=>'10.0.2.33','Type'=>'RDP/Terminal-Server','Kind'=>'VM','Connections'=>[
['connType'=>'LDAP','target'=>'dc01','targetType'=>'hostname']
]],
['Hostname'=>'mon01','IP'=>'10.0.2.34','Type'=>'Monitoring','Kind'=>'VM','Connections'=>[
['connType'=>'SNMP','target'=>'core01','targetType'=>'hostname'],
['connType'=>'SNMP','target'=>'fw01','targetType'=>'hostname'],
['connType'=>'SNMP','target'=>'nas01','targetType'=>'hostname']
]],
['Hostname'=>'bkp01','IP'=>'10.0.2.35','Type'=>'Backup','Kind'=>'VM','Connections'=>[
['connType'=>'Backup-Agent','target'=>'app01','targetType'=>'hostname'],
['connType'=>'Backup-Agent','target'=>'db01','targetType'=>'hostname'],
['connType'=>'NFS','target'=>'nas02','targetType'=>'hostname']
]],
['Hostname'=>'nas01','IP'=>'10.0.4.10','Type'=>'Storage/NAS','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'nas02','IP'=>'10.0.4.11','Type'=>'Storage/NAS','Kind'=>'Physisch','Notes'=>'Backup-Ziel','Connections'=>[]],
['Hostname'=>'pc-buero1','IP'=>'10.0.10.21','Type'=>'PC','Kind'=>'Physisch','Connections'=>[
['connType'=>'HTTPS','target'=>'web01','targetType'=>'hostname','port'=>443],
['connType'=>'SSH','target'=>'lnx01','targetType'=>'hostname'],
['connType'=>'RDP','target'=>'rds01','targetType'=>'hostname'],
['connType'=>'SMB','target'=>'nas01','targetType'=>'hostname']
]],
['Hostname'=>'pc-buero2','IP'=>'10.0.10.22','Type'=>'PC','Kind'=>'Physisch','Connections'=>[
['connType'=>'RDP','target'=>'rds01','targetType'=>'hostname']
]],
['Hostname'=>'pc-empfang','IP'=>'10.0.10.23','Type'=>'PC','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'nb-chef','IP'=>'10.0.20.31','Type'=>'Laptop','Kind'=>'Physisch','Connections'=>[
['connType'=>'HTTPS','target'=>'web01','targetType'=>'hostname','port'=>443]
]],
['Hostname'=>'nb-technik','IP'=>'10.0.20.32','Type'=>'Laptop','Kind'=>'Physisch','Connections'=>[
['connType'=>'SSH','target'=>'core01','targetType'=>'hostname']
]],
['Hostname'=>'handy-flo','IP'=>'10.0.20.33','Type'=>'Smartphone','Kind'=>'Physisch','Connections'=>[
['connType'=>'HTTPS','target'=>'mail01','targetType'=>'hostname','port'=>443]
]],
['Hostname'=>'tablet-lager','IP'=>'10.0.20.34','Type'=>'Tablet','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'hotspot01','IP'=>'10.0.20.1','Type'=>'Hotspot','Kind'=>'Physisch','Notes'=>'Notfall-Uplink (LTE)','Connections'=>[]],
['Hostname'=>'prn-eg','IP'=>'10.0.10.40','Type'=>'Drucker','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'cam-hof','IP'=>'10.0.10.41','Type'=>'Kamera','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'tel-empfang','IP'=>'10.0.10.42','Type'=>'VoIP-Telefon','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'iot-heizung','IP'=>'10.0.10.43','Type'=>'IoT-Gerät','Kind'=>'Physisch','Connections'=>[]],
['Hostname'=>'cloud-crm','IP'=>'203.0.113.50','Type'=>'Cloud-Dienst','Kind'=>'Extern','Connections'=>[]]
]
];
#bevorzugt die grosse bsp.json aus dem repo, eingebautes demo als fallback
$bspUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/networking/bsp.json';
$ctx=stream_context_create(['http'=>['timeout'=>10]]);
$remote=@file_get_contents($bspUrl,false,$ctx);
if($remote!==false){
$tmp=json_decode($remote,true);
if(is_array($tmp)&&isset($tmp['devices'])&&is_array($tmp['devices'])&&count($tmp['devices'])>0)$demo=$tmp;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<?php $__th=(isset($networking_theme)&&in_array($networking_theme,['light','dark'],true))?$networking_theme:'auto'; ?><html lang="de"<?= $__th==='light'?' data-theme="light"':($__th==='dark'?' data-theme="dark"':'') ?>>
<head>
<meta charset="utf-8">
<title>Beispiel laden…</title>
<style id="theme-tokens">
:root{
--bg:#ffffff;--fg:#141414;--muted:#5a5f66;--border:#d5d7dd;
--panel:#f5f6f8;--panel2:#e9ebef;--panelhead:#eef0f3;
--accent:#0a63ff;--accent-fg:#ffffff;--link:#0a58ca;
--danger:#b3261e;--danger-bg:#fde7e7;--danger-fg:#7f1512;
--success:#1a7f37;--success-bg:#e6f4ea;--warn:#8a6d00;--warn-bg:#fff4cf;
--ctrl-bg:#141414;--ctrl-fg:#ffffff;--shadow:rgba(0,0,0,.18);
}
:root[data-theme="dark"]{
--bg:#0f1113;--fg:#e8e8ea;--muted:#a6adb6;--border:#2b3038;
--panel:#17191d;--panel2:#20242a;--panelhead:#20242a;
--accent:#5b9bff;--accent-fg:#0a0d12;--link:#7db0ff;
--danger:#ff6b6b;--danger-bg:#3f1d1d;--danger-fg:#ffd9d9;
--success:#5bd47e;--success-bg:#163a22;--warn:#e6c34a;--warn-bg:#3a3212;
--ctrl-bg:#20242a;--ctrl-fg:#e8e8ea;--shadow:rgba(0,0,0,.6);
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]):not([data-theme="dark"]){
--bg:#0f1113;--fg:#e8e8ea;--muted:#a6adb6;--border:#2b3038;
--panel:#17191d;--panel2:#20242a;--panelhead:#20242a;
--accent:#5b9bff;--accent-fg:#0a0d12;--link:#7db0ff;
--danger:#ff6b6b;--danger-bg:#3f1d1d;--danger-fg:#ffd9d9;
--success:#5bd47e;--success-bg:#163a22;--warn:#e6c34a;--warn-bg:#3a3212;
--ctrl-bg:#20242a;--ctrl-fg:#e8e8ea;--shadow:rgba(0,0,0,.6);
}}
</style>
</head>
<body>
<script>
try{sessionStorage.setItem('networking_demo',<?php echo json_encode(json_encode($demo,JSON_UNESCAPED_UNICODE),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);?>);}catch(e){}
location.replace('?');
</script>
</body>
</html>
