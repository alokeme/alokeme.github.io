<?php declare(strict_types=1);
return [
 'app'=>['name'=>'MOJ Judicial System','env'=>getenv('APP_ENV')?:'production','url'=>getenv('APP_URL')?:'http://localhost/MOJ-Judicial-System/public','timezone'=>'Asia/Riyadh','key'=>getenv('APP_KEY')?:'change-this-32-byte-secret-key-now'],
 'db'=>['dsn'=>getenv('DB_DSN')?:'mysql:host=127.0.0.1;dbname=moj_judicial;charset=utf8mb4','user'=>getenv('DB_USER')?:'root','pass'=>getenv('DB_PASS')?:'','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]],
 'moj'=>['base_uri'=>'https://laws-gateway.moj.gov.sa/apis/legislations/v1','site_url'=>'https://laws.moj.gov.sa','timeout'=>30,'retry'=>3,'rate_per_second'=>2],
 'security'=>['session_name'=>'MOJSESSID','csrf_key'=>'_csrf','remember_days'=>30,'max_login_attempts'=>5,'lock_minutes'=>15],
 'paths'=>['logs'=>__DIR__.'/../logs','cache'=>__DIR__.'/../storage/cache','backups'=>__DIR__.'/../storage/backups','exports'=>__DIR__.'/../storage/exports']
];
