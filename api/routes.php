<?php
// api/routes.php

// Endpoints de la Fase 1
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');