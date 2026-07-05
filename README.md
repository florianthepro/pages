### Projekte-[index](https://github.com/florianthepro/pages/tree/main/content/instances)

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
powershell -NoProfile -ExecutionPolicy Bypass -Command "iex (irm 'https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/start_apache_temp.bat')"
```
