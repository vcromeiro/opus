<?php

return array(


    'pdf' => array(
        'enabled' => true,
        'binary'  => env('WKHTMLTOPDF_PATH', 'wkhtmltopdf'),
        'timeout' => false,
        'options' => [],
        'env'     => [],
    ),


);
