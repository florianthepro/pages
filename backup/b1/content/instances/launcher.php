<?php
declare(strict_types=1);
///////////////////////
$links=<<<'YAML'
general:
  - {title:"Books",url:"https://books.tcsoc.net/books/tco-tanum-consult",icon:"files/ico/books.tcsoc.ico"}
  - {title:"OTRS - Znuny",url:"https://servicedesk.tcsoc.net/otrs/index.pl",icon:"files/ico/servicedesk.tcsoc.ico"}
  - {title:"ScreenConnect",url:"https://help.tcsoc.net/Host#Access/",icon:"files/ico/help.tcsoc.ico"}
  - {title:"Puplic Map",url:"https://monitoring.tcsoc.net/public/mapshow.htm?ids=15901:4C3A7306-367D-40E8-8CD6-A9EC9936D655,16089:577EA9F7-7D38-45C5-A52B-159D3800C66C,14848:6AD7B66E-A56E-4694-8296-D0A24FAC3AED",icon:"files/ico/monitoring.tcsoc.ico"}
  - {title:"1 Password",url:"https://tanum.1password.com/",icon:"files/ico/tanum.1password.ico"}
  - {title:"Okta",url:"https://tanum.okta.com/",icon:"files/ico/okta.ico"}
  - {title:"Rapid 7",url:"https://insight.rapid7.com/login?sso=true",icon:"files/ico/insight.rapid7.ico"}
  - {title:"Bitdefender",url:"https://cloudgz.gravityzone.bitdefender.com/",icon:"files/ico/gravityzone.bitdefender.ico"}
  - {title:"Zeiterfassung",url:"https://gamov.tanum.de/projectile/start",icon:"files/ico/gamov.tanum.ico"}
  - {title:"OTRS Reporting",url:"http://minkowski.tcsoc.net/reporting/",icon:"files/ico/minkowski.tcsoc.ico"}
  - {title:"Mylunch",url:"https://mylunch.apetito.de/",icon:"files/ico/mylunch.apetito.ico"}
  - {title:"Einsatzplanung",url:"https://tanum.sharepoint.com/:x:/r/sites/tanum/_layouts/15/Doc.aspx?sourcedoc=%7B31FFB742-7B78-4058-93B4-278C470592DC%7D&file=Einsatzplanung%202026.xlsx",icon:"files/ico/einsatzplanung.sharepoint.ico"}
  - {title:"tco-zeiterfassung",url:"https://tanum.sharepoint.com/:x:/r/sites/tanum/_layouts/15/Doc.aspx?sourcedoc=%7B5E3A1CAD-BFB0-4B8A-80CE-8F8DEE86E723%7D&file=TCO_Zeiterfassung.xlsx",icon:"files/ico/zeiterfassung.sharepoint.ico"}
  - {title:"Mein",url:"https://mein.apetito.de/",icon:"files/ico/mein.apetito.ico"}
  - {title:"CDC - Share Point",url:"https://cdcit.sharepoint.com/sites/chiccoDokumente/chiccodicaff/Forms/AllItems.aspx",icon:"files/ico/cdc-sharepoint.ico"}
  - {title:"ScreenConnect - Clients",url:"https://books.tcsoc.net/books/remote-help-access/page/operation",icon:"files/ico/screenconnect-clients.books.ico"}
  - {title:"Datev Dash",url:"https://apps.datev.de/ano/de/",icon:"files/ico/apps.datev.ico"}
  - {title:"Datev Link",url:"https://apps.datev.de/ano/dashboard/",icon:"files/ico/v2.link.datev.ico"}
  - {title:"Organisationshandbuch",url:"https://tanum.sharepoint.com/sites/Organisationshandbuch/",icon:"files/ico/organisationshandbuch.ico"}
  - {title:"OTRS",url:"/otrs/",icon:"https://raw.githubusercontent.com/florianthepro/pages/main/content/media/csv-reporting/index.svg"}
m365:
  - {title:"All MS Portals",url:"https://msportals.io/",icon:"files/ico/msportals.ico"}
  - {title:"Admin",url:"https://admin.cloud.microsoft/",icon:"files/ico/admin.microsoft.ico"}
  - {title:"Entra",url:"https://entra.microsoft.com/",icon:"files/ico/entra.microsoft.ico"}
  - {title:"Intunes",url:"https://intune.microsoft.com/",icon:"files/ico/intune.microsoft.ico"}
  - {title:"Share Point",url:"https://tanum.sharepoint.com/sites/tanum/SitePages/CollabHome.aspx",icon:"files/ico/sharepoint.ico"}
  - {title:"Settings",url:"https://myaccount.microsoft.com/",icon:"files/ico/settings.m365.ico"}
  - {title:"Apps",url:"https://myapplications.microsoft.com/",icon:"files/ico/apps.m365.ico"}
  - {title:"One Drive",url:"https://tanum-my.sharepoint.com/",icon:"files/ico/onedrive.ico"}
  - {title:"Outlook",url:"https://outlook.office365.com/",icon:"files/ico/outlook.ico"}
  - {title:"Planner",url:"https://planner.cloud.microsoft/webui/",icon:"files/ico/planner.ico"}
operation:
  - {title:"Tools",url:"https://tools.xo.je",icon:"files/ico/tools-launcher.ico"}
  - {title:"DHL",url:"https://dhl.de/",icon:"files/ico/dhl.ico"}
  - {title:"Servicedesk Rules",url:"https://books.tcsoc.net/books/service-desk-handbook/page/servicedesk-rules",icon:"files/ico/servicdesk-rules.ico"}
  - {title:"NTP",url:"https://www.zeitserver.de/deutschland/ptb-zeitserver-in-braunschweig/",icon:"files/ico/ntp.ico"}
  - {title:"Wareneingang",url:"https://books.tcsoc.net/books/ticket-system/page/wareneingang-cdc",icon:"files/ico/wareneingang.ico"}
  - {title:"tco-grundsysteme-overview",url:"https://books.tcsoc.net/books/tco-tanum-consult/page/tco-grundsysteme-overview",icon:"files/ico/v5-!.ico"}
  - {title:"Redirect",url:"files/redirect.html",icon:"files/ico/arrow.ico"}
  - {title:"hamburgerei",url:"https://wolt.com/de/deu/munich/search?q=hamburgerei",icon:"files/ico/hamburgerei.ico"}
  - {title:"Riemarcaden",url:"https://www.riemarcaden.de/",icon:"files/ico/riem-arcaden.ico"}
YAML;

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
blocked: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/blocked/index.html"
index: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/launcher/v1.php"
YAML;
///////////////////////
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__loaderFile=sys_get_temp_dir().'/florian_pages_loader.php';
$__loaderCode=file_get_contents($__loaderUrl);
if($__loaderCode===false){http_response_code(500);exit('Loader konnte nicht geladen werden.');}
if(file_put_contents($__loaderFile,$__loaderCode,LOCK_EX)===false){http_response_code(500);exit('Loader konnte nicht gespeichert werden.');}
require $__loaderFile;
