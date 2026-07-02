### Projekte: [index](https://github.com/florianthepro/pages/tree/main/index)

## je nach projekt webserver beschreibar machen:

# Debian/Ubuntu (www-data):
```
sudo bash -c 'WEBROOT=/var/www/html; USER=www-data; sudo chown -R $USER:$USER "$WEBROOT" && sudo chmod -R u+rwX,g+rwX,o-rwx "$WEBROOT"'
```
# RHEL/CentOS (apache):
```
sudo bash -c 'WEBROOT=/var/www/html; USER=apache; sudo chown -R $USER:$USER "$WEBROOT" && sudo chmod -R u+rwX,g+rwX,o-rwx "$WEBROOT"'
```
