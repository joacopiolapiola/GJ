# GJ

Proyecto web desarrollado con PHP y MongoDB.

## 📋 Requisitos Previos

- **Desarrollo Local**: Laragon (Windows)
- **Servidor**: Ubuntu 20.04+
- **Base de Datos**: MongoDB
- **PHP**: 8.0+
- **Composer**: Gestor de dependencias PHP

---

## 🚀 Instalación Local (Laragon - Windows)

### 1. Descargar e Instalar Laragon

1. Descarga Laragon desde [https://laragon.org/download/](https://laragon.org/download/)
2. Ejecuta el instalador
3. Durante la instalación, selecciona:
   - PHP 8.0+ (o la versión deseada)
   - MySQL/MariaDB (aunque usaremos MongoDB)
   - Node.js (opcional)
   - Git (recomendado)

### 2. Instalar MongoDB en Laragon

1. Abre Laragon
2. Haz clic en **Menu → Tools → Quick Add** (o accede a la carpeta de extensiones)
3. Descarga MongoDB desde [https://www.mongodb.com/try/download/community](https://www.mongodb.com/try/download/community)
4. Instala MongoDB Community Server en `C:\laragon\bin\mongodb\` (o ajusta según tu instalación)
5. En Laragon, habilita MongoDB en las extensiones

### 3. Instalar el Proyecto

```bash
# 1. Clonar el repositorio en la carpeta www de Laragon
cd C:\laragon\www
git clone https://github.com/joacopiolapiola/GJ.git
cd GJ

# 2. Instalar dependencias PHP con Composer
composer install

# 3. Crear archivo de configuración
cp .env.example .env

# 4. Configurar base de datos en .env
# Edita el archivo .env y establece:
# DB_CONNECTION=mongodb
# DB_HOST=127.0.0.1
# DB_PORT=27017
# DB_DATABASE=gj

# 5. Generar clave de aplicación (si usas Laravel)
php artisan key:generate

# 6. Ejecutar migraciones (si las hay)
php artisan migrate
```

### 4. Iniciar el Servidor Local

1. Abre Laragon
2. Asegúrate de que Apache/Nginx esté activado
3. Verifica que MongoDB esté corriendo
4. Accede a `http://localhost/GJ` en tu navegador

---

## 🔧 Instalación en VPS Ubuntu

### 1. Actualizar Sistema

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y curl wget git nano
```

### 2. Instalar PHP 8.0+

```bash
# Agregar repositorio PHP
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP y extensiones necesarias
sudo apt install -y php8.0 php8.0-fpm php8.0-cli php8.0-curl php8.0-gd php8.0-json php8.0-mbstring php8.0-xml php8.0-zip php8.0-mongodb

# Verificar instalación
php -v
```

### 3. Instalar MongoDB

```bash
# Importar clave GPG
wget -qO - https://www.mongodb.org/static/pgp/server-5.0.asc | sudo apt-key add -

# Agregar repositorio
echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu focal/mongodb-org/5.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-5.0.list

# Instalar MongoDB
sudo apt update
sudo apt install -y mongodb-org

# Iniciar servicio
sudo systemctl start mongod
sudo systemctl enable mongod

# Verificar estado
sudo systemctl status mongod
```

### 4. Instalar Composer

```bash
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
composer --version
```

### 5. Instalar Nginx (o Apache)

#### Opción A: Nginx (Recomendado)

```bash
sudo apt install -y nginx

# Iniciar servicio
sudo systemctl start nginx
sudo systemctl enable nginx

# Crear bloque de servidor
sudo nano /etc/nginx/sites-available/gj
```

Añade el siguiente contenido:

```nginx
server {
    listen 80;
    server_name tu_dominio.com www.tu_dominio.com;
    root /var/www/gj/public;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Habilita el sitio:

```bash
sudo ln -s /etc/nginx/sites-available/gj /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### Opción B: Apache

```bash
sudo apt install -y apache2 libapache2-mod-php8.0

# Habilitar módulos necesarios
sudo a2enmod rewrite
sudo a2enmod php8.0
sudo a2enmod ssl

sudo systemctl start apache2
sudo systemctl enable apache2
```

### 6. Descargar y Configurar el Proyecto

```bash
# Crear directorio
sudo mkdir -p /var/www/gj
cd /var/www/gj

# Clonar repositorio
sudo git clone https://github.com/joacopiolapiola/GJ.git .

# Instalar dependencias
sudo composer install --no-dev --optimize-autoloader

# Crear archivo de configuración
sudo cp .env.example .env
sudo nano .env
```

Configura en `.env`:

```env
APP_NAME=GJ
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu_dominio.com

DB_CONNECTION=mongodb
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=gj
```

### 7. Configurar Permisos

```bash
sudo chown -R www-data:www-data /var/www/gj
sudo chmod -R 755 /var/www/gj
sudo chmod -R 775 /var/www/gj/storage
sudo chmod -R 775 /var/www/gj/bootstrap/cache
```

### 8. Generar Clave de Aplicación (si usas Laravel)

```bash
php artisan key:generate
```

### 9. Configurar SSL con Let's Encrypt (HTTPS)

```bash
sudo apt install -y certbot python3-certbot-nginx

# Obtener certificado
sudo certbot certonly --standalone -d tu_dominio.com -d www.tu_dominio.com

# Renovación automática
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

### 10. Verificar que Todo Funciona

```bash
# Verificar PHP
php -v

# Verificar MongoDB
mongosh
# En la consola de MongoDB ejecuta: db.version()

# Verificar servicios
sudo systemctl status nginx      # o apache2
sudo systemctl status php8.0-fpm
sudo systemctl status mongod
```

---

## 📁 Estructura del Proyecto

```
GJ/
├── app/              # Código de la aplicación
├── config/           # Archivos de configuración
├── database/         # Migraciones y seeders
├── public/           # Archivos públicos
├── resources/        # Vistas y assets
├── routes/           # Rutas de la aplicación
├── storage/          # Almacenamiento temporal
├── bootstrap/        # Bootstrap de la aplicación
├── .env.example      # Ejemplo de variables de entorno
├── composer.json     # Dependencias PHP
└── README.md         # Este archivo
```

---

## 🔍 Comandos Útiles

### Desarrollo Local (Laragon)

```bash
# Ver logs
tail -f storage/logs/laravel.log

# Ejecutar servidor de desarrollo
php artisan serve

# Ejecutar migraciones
php artisan migrate

# Limpiar caché
php artisan cache:clear
php artisan config:clear
```

### Servidor Ubuntu

```bash
# Ver estado de servicios
sudo systemctl status nginx      # o apache2
sudo systemctl status php8.0-fpm
sudo systemctl status mongod

# Ver logs
tail -f /var/log/nginx/error.log
tail -f /var/log/php-fpm.log

# Acceder a MongoDB
mongosh
use gj
db.colecciones.find()
```

---

## 🚨 Solución de Problemas

### MongoDB no conecta
```bash
# Verificar que MongoDB está corriendo
sudo systemctl status mongod

# Reiniciar MongoDB
sudo systemctl restart mongod
```

### Permisos denegados en storage
```bash
sudo chown -R www-data:www-data /var/www/gj/storage
sudo chmod -R 775 /var/www/gj/storage
```

### Error 500 en producción
```bash
# Verificar logs
tail -f /var/log/nginx/error.log

# Verificar permisos de .env
sudo chmod 644 /var/www/gj/.env

# Limpiar caché
cd /var/www/gj
sudo php artisan cache:clear
sudo php artisan config:clear
```

---

## 📝 Variables de Entorno (.env)

```env
# Aplicación
APP_NAME=GJ
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=https://tu_dominio.com

# Base de Datos MongoDB
DB_CONNECTION=mongodb
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=gj
DB_USERNAME=
DB_PASSWORD=

# Mail (opcional)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@gj.local
```

---

## 🤝 Contribuir

Para contribuir al proyecto:

1. Haz un fork del repositorio
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit los cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

---

## 📄 Licencia

Este proyecto está bajo licencia [especificar licencia].

---

## 📧 Contacto

Para preguntas o soporte: [tu email o información de contacto]

---

**Última actualización**: 2026-09-11
