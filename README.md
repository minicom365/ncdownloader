- [English](README.md)
- [简体中文](README.zh-CN.md)

An easy-to-use web interface for Aria2 and youtube-dl

- Search for torrents within the app from mutiple BT sites
- Control Aria2 and manage download tasks from the web;
- Harnessing the power of youtube-dl to download videos from 700+ video sites(youtube,youku,dailymotion,twitter,facebook and the likes;
<img width="800" alt="nc2" src="https://user-images.githubusercontent.com/3911975/132008308-dec2a7ba-4387-441e-9ded-538d61fbccf0.png">
<img width="800" alt="nc4" src="https://user-images.githubusercontent.com/3911975/142444998-54dd54a6-0c8e-4d49-8188-270964a99c50.png">
<img width="800" alt="nc5" src="https://user-images.githubusercontent.com/3911975/142445020-27ec389a-5437-4d28-acc0-5e757fd6897d.png">

### How to use

NCDownloader has included both yt-dlp(faster version of youtube-dl) and aria2c and there is no need for manual installation under normal circumstances (*tested it successfully with snap version of nextcloud both in centos7 and ubuntu 20.04*)   
But if for some reason,the builtin binaries don't work for you, then you will need to install them yourself

#### installing aria2 and yt-dlp in ubuntu
```bash
sudo apt install aria2
sudo curl -L https://github.com/yt-dlp/yt-dlp/releases/download/2022.05.18/yt-dlp 4 -o /usr/local/bin/youtube-dl
sudo chmod a+rx /usr/local/bin/youtube-dl
```
Also, if you don't want to use the builtin versions, you can always force the app use a specific version by setting the binary path manually. In that case, the app will not try to find youtube-dl binary in your system, and the built-in ones will be ignored as well. 

### How to build front-end code

NPM 7.0+ and node 14.0.0+ are required to build front-end scripts

```bash
#start to build
npm run build

#installing php dependencies
composer install
```

#### Nextcloud App homepage
https://apps.nextcloud.com/apps/ncdownloader

#### Docker Deployment & Persistent Setup (Manual Install)

When manually deploying NCDownloader inside a Nextcloud Docker container via `git clone`, build artifacts are omitted by default due to `.gitignore` exclusions. This manual deployment requires custom frontend compilation, backend autoloader assembly, and persistent binary injection to prevent runtime errors (e.g., blank screens, missing `autoload.php`, or incompatible CPU architecture `Signal 11` Segmentation Faults on `aria2c`).

Follow these sequential steps on your host server to perform a clean, persistent manual installation:

##### 1. Clean Existing Assets & Deploy Built Source
```bash
# 1. Remove any corrupt or nested ncdownloader directory inside the container
docker exec -u 0 -it <container_name> rm -rf /var/www/html/custom_apps/ncdownloader

# 2. Pull the latest compiled release branch on the host
cd ncdownloader
git pull
cd ..

# 3. Copy the compiled source directory into the Nextcloud custom apps folder
docker cp ncdownloader <container_name>:/var/www/html/custom_apps/

# 4. Set appropriate web server ownership (www-data)
docker exec -u 0 -it <container_name> chown -R www-data:www-data /var/www/html/custom_apps/ncdownloader
```

##### 2. Restore PHP Autoloader (Composer Dependencies)
Execute Composer within the container's native PHP environment to restore crucial vendor libraries safely.
```bash
# 1. Download composer.phar on the host server
curl -LO https://getcomposer.org/composer.phar

# 2. Copy composer.phar into the container's app directory
docker cp composer.phar <container_name>:/var/www/html/custom_apps/ncdownloader/composer.phar

# 3. Install production dependencies natively to generate 'vendor/autoload.php'
docker exec --user www-data -it <container_name> php /var/www/html/custom_apps/ncdownloader/composer.phar install --working-dir=/var/www/html/custom_apps/ncdownloader --no-dev

# 4. Clean up the temporary composer binary from both host and container
rm -f composer.phar
docker exec -u 0 -it <container_name> rm -f /var/www/html/custom_apps/ncdownloader/composer.phar
```

##### 3. Inject Architecture-Native, Persistent Aria2 Binary
Pre-compiled binaries may crash with `Signal 11` (Segmentation Fault) if they do not match the target CPU architecture (e.g., ARM64, x86_64). Inject a natively compiled package into the persistent volume to guarantee lifetime persistence across container upgrades and recreation.
```bash
# 1. Install the native package temporarily inside the running container to match the exact CPU architecture
docker exec -u 0 -it <container_name> apt-get update
docker exec -u 0 -it <container_name> apt-get install -y aria2

# 2. Create the persistent binary folder inside the app's directory
docker exec -u 0 -it <container_name> mkdir -p /var/www/html/custom_apps/ncdownloader/bin

# 3. Copy the native, verified working binary into the persistent volume path
docker exec -u 0 -it <container_name> cp -f /usr/bin/aria2c /var/www/html/custom_apps/ncdownloader/bin/aria2c

# 4. Set final ownership and executable permissions
docker exec -u 0 -it <container_name> chown -R www-data:www-data /var/www/html/custom_apps/ncdownloader/bin
docker exec -u 0 -it <container_name> chmod +x /var/www/html/custom_apps/ncdownloader/bin/aria2c
```

After performing these steps, reload your Nextcloud interface and perform a **hard refresh (`Ctrl + F5` or `Cmd + Shift + R`)** to clear browser cache and load the fully functional interface.