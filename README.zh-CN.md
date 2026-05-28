- [English](README.md)
- [简体中文](README.zh-CN.md)

运行在nextcloud下的多功能下载工具（Aria2 and youtube-dl）

- 内置种子搜索工具，可以多个网站搜索，直接APP内下载
- 无需手动配置Aria2，支持web界面配置和开启
- 利用youtube-dl的强大功能，可从数百个网站下载音视频文件
<img width="800" alt="nc2" src="https://user-images.githubusercontent.com/3911975/132008308-dec2a7ba-4387-441e-9ded-538d61fbccf0.png">
<img width="800" alt="nc4" src="https://user-images.githubusercontent.com/3911975/142444998-54dd54a6-0c8e-4d49-8188-270964a99c50.png">
<img width="800" alt="nc5" src="https://user-images.githubusercontent.com/3911975/142445020-27ec389a-5437-4d28-acc0-5e757fd6897d.png">

### 如何使用

最新版本已经自带aria2c和youtube-dl程序 (*在centos7 and ubuntu 20.04上测试过nextcloud的snap版本，可正常运行*)   
但如果自带的程序无法在你的系统正常运行，那你就得自己安装youtube-dl和aria2c了
#### 在ubuntu下安装aria2 and youtube-dl
```bash
sudo apt install aria2
sudo curl -L https://yt-dl.org/downloads/latest/youtube-dl 4 -o /usr/local/bin/youtube-dl
sudo chmod a+rx /usr/local/bin/youtube-dl
```
本地安装的版本优先于自带的版本
但是你可以通过在app内设置，强制使用特定版本的aria2或youtube-dl

#### 生成前端代码
需要安装NPM 7.0+ and node 14.0.0+
```bash
#start to build
npm run build

#installing php dependencies
composer install
```

#### Nextcloud App homepage
https://apps.nextcloud.com/apps/ncdownloader

#### Docker 部署与持久化配置（手动安装）

在 Nextcloud Docker 容器中通过 `git clone` 手动部署 NCDownloader 时，由于 `.gitignore` 的排除规则，默认会缺少前端和后端的构建产物。这种手动部署方式需要重新编译前端、构建后端自动加载器，并进行持久化二进制文件注入，以防止运行时错误（例如：白屏、找不到 `autoload.php` 或由于 CPU 架构不匹配导致的 `aria2c` 进程崩溃 `Signal 11` 段错误）。

请在您的宿主机上按照以下步骤进行完整、持久的手动安装部署：

##### 1. 清理现有资源并部署构建源码
```bash
# 1. 彻底清除容器内原有异常或嵌套的 ncdownloader 文件夹
docker exec -u 0 -it <container_name> rm -rf /var/www/html/custom_apps/ncdownloader

# 2. 在宿主机拉取包含完整前端构建产物的最新 release 分支
cd ncdownloader
git pull
cd ..

# 3. 将已构建好的源码复制到 Nextcloud custom apps 目录下
docker cp ncdownloader <container_name>:/var/www/html/custom_apps/

# 4. 重新指定 Web 服务器所有权 (www-data)
docker exec -u 0 -it <container_name> chown -R www-data:www-data /var/www/html/custom_apps/ncdownloader
```

##### 2. 修复 PHP 自动加载器（Composer 依赖包）
在容器原生的 PHP 运行环境中执行 Composer，安全地重建后端库依赖。
```bash
# 1. 在宿主机下载官方 composer.phar 归档包
curl -LO https://getcomposer.org/composer.phar

# 2. 将 composer.phar 复制到容器内的应用目录中
docker cp composer.phar <container_name>:/var/www/html/custom_apps/ncdownloader/composer.phar

# 3. 运行容器内原生 PHP 还原生产环境依赖，自动生成 'vendor/autoload.php'
docker exec --user www-data -it <container_name> php /var/www/html/custom_apps/ncdownloader/composer.phar install --working-dir=/var/www/html/custom_apps/ncdownloader --no-dev

# 4. 彻底清理宿主机和容器内临时使用的 composer 文件
rm -f composer.phar
docker exec -u 0 -it <container_name> rm -f /var/www/html/custom_apps/ncdownloader/composer.phar
```

##### 3. 注入支持架构持久保存的 Aria2 运行文件
如果预编译二进制文件与当前 CPU 架构（如 ARM64, x86_64）不匹配，会直接报 `Signal 11`（段错误）并崩溃。在此，我们把容器原生编译并测试通过的二进制文件注入到挂载卷中，以确保容器重建或升级时免安装且长效运行。
```bash
# 1. 在运行中的容器内，通过 apt 临时拉取适配当前 CPU 架构的纯净 aria2 包
docker exec -u 0 -it <container_name> apt-get update
docker exec -u 0 -it <container_name> apt-get install -y aria2

# 2. 在应用的持久化映射卷目录中创建 bin 目录
docker exec -u 0 -it <container_name> mkdir -p /var/www/html/custom_apps/ncdownloader/bin

# 3. 将容器内已经适配且运行无误的官方原生二进制文件复制到持久化 bin 目录中！
docker exec -u 0 -it <container_name> cp -f /usr/bin/aria2c /var/www/html/custom_apps/ncdownloader/bin/aria2c

# 4. 为复制过来的二进制文件赋予最终所有权及可执行权限
docker exec -u 0 -it <container_name> chown -R www-data:www-data /var/www/html/custom_apps/ncdownloader/bin
docker exec -u 0 -it <container_name> chmod +x /var/www/html/custom_apps/ncdownloader/bin/aria2c
```

完成上述配置后，返回浏览器访问您的 Nextcloud，并执行**强刷刷新（`Ctrl + F5` 或 `Cmd + Shift + R`）**清空浏览器缓存，即可开启完整、无故障的下载管理界面。