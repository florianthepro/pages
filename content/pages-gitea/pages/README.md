### Projekte-[index](https://gitea.getitsec.com/florianthepro/pages/src/branch/main/content/instances)

---

#### je nach projekt webserver beschreibar machen:

##### Debian/Ubuntu (www-data):
```
sudo bash -c 'WEBROOT=/var/www/html; USER=www-data; sudo chown -R $USER:$USER "$WEBROOT" && sudo chmod -R u+rwX,g+rwX,o-rwx "$WEBROOT"'
```
##### RHEL/CentOS (apache):
```
sudo bash -c 'WEBROOT=/var/www/html; USER=apache; sudo chown -R $USER:$USER "$WEBROOT" && sudo chmod -R u+rwX,g+rwX,o-rwx "$WEBROOT"'
```

---

apache testing (ps tmp):
```
powershell -NoProfile -ExecutionPolicy Bypass -Command "iex (irm 'https://gitea.getitsec.com/florianthepro/pages/raw/branch/main/content/loader/start_apache_temp.bat')"
```
```
curl -sS -o "%TEMP%\tmp_start.bat" "https://gitea.getitsec.com/florianthepro/pages/raw/branch/main/content/loader/tmp-apache.bat" && cmd /c "%TEMP%\tmp_start.bat"
```
