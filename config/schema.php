<?php
$is_schema = (env('APP_ENV', 'local') == 'production' || env('APP_ENV', 'local') == 'development')?true:false;
return [
    'notification'=>$is_schema?env('DB_NOTIFICATION_SCHEMA', 'notification'):'public',
    'upload'=>$is_schema?env('DB_UPLOAD_SCHEMA', 'upload'):'public',
    'user'=>$is_schema?env('DB_USER_SCHEMA', 'user'):'public',
];
