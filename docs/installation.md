Installation and Production Instructions
========================================

*Last update : 2019/01/04*

Feel free to add some more informations if you solve installation issues !

Quick install
-------------

- **Debian script**: there is a script for debian installation named install_debian.sh in this docs directory ! After installation, go to http://localhost/GoGoCarto/web/app_dev.php/project/initialize to initialize your project.

- **Docker containers**: please follow the instructions [here](installation_docker.md).

Requirements
------------

1. Php (Sur Linux : php7.2-curl)
2. [Composer](https://getcomposer.org/download/) 
3. [Nodejs](https://nodejs.org/en/download/)
4. [Git](https://git-scm.com/)
5. Web Server (Apache, Ngninx, [Wamp server](http://www.wampserver.com/) ...)
6. MongoDB (http://php.net/manual/fr/mongodb.installation.php)

The project is using php5. **If you want to use php7**, you will need to install the [MongoPhpAdapter](https://github.com/alcaeus/mongo-php-adapter)
To do so, please run :
```
composer config "platform.ext-mongo" "1.6.16" && composer require alcaeus/mongo-php-adapter
```

Consider the [Docker installation](installation_docker.md) if you run into troubles installing these softwares.

Installation
------------

### Cloning repo (clone dev branch)
```
cd path-to-php-server-folder (default linux /var/www/html, windows c:/wamp/www... )
git clone https://github.com/pixelhumain/GoGoCarto
cd GoGoCarto/
```

### Installing dependencies 
Php dependency (symfony, bundles...) 
```
composer install
```
*During installation, app/config/parameters.yml file will be created, leave default fields*

Workflow dependencies (compiling sass and javascript)
```
npm install gulp
npm install
```

Start
-----
Dumping assets
```
php bin/console assets:install --symlink web
```

First build of Javascript and Css
```
gulp build
```

Start watching for file change (automatic recompile)
```
gulp watch
```


Generate Database
-----------------

Go to symfony console : http://localhost/GoGoCarto/web/app_dev.php/_console
Run the followings command
```
doctrine:mongodb:schema:create
doctrine:mongodb:generate:hydrators
doctrine:mongodb:generate:proxies
doctrine:mongodb:fixtures:load
```

The last command will generate a basic configuration

Then generate if necessary random point on the map :
app:elements:generate 200

Now initialize your project with following route
http://localhost/GoGoCarto/web/app_dev.php/project/initialize

Updating your install
---------------------

Each time you update GoGoCarto code, please run the following commands (first one is most important)
```
php bin/console db:migrate
gulp build
php bin/console cache:clear
```

Production
----------

1. Dump assetic in symfony console to update the web/templates files
```assetic:dump```

2. Generate compressed js and css files
```
gulp build
gulp production
```

3. enable gz compression in your web server

4. In the distant console (http://yoursite.com/web/app_dev.php/_console)
```
cache:clear --env=prod
```

5. Make sure that the var folder is writable ```chmod -R 771 var/```



Issues
-------

If you creating an element you ahev the issue "cannot load method bcmod from namespace ..."
You need to install the bc-math module
```
sudo apt install php7.0-bcmath
```

If memory limits while using composer
```
COMPOSER_MEMORY_LIMIT=-1 composer ...
```