<?php

$mysql = array(
    'host' => 'localhost',
    'name' => 'database_name',
    'user' => 'database_user',
    'pass' => 'password',
    'type' => 'mysql' // possible mysqli
);

$config = array(
    'server' => 'x.x.x.x', // remote ircd server
    'port' => '4400',
    'cline' => 'Service.amservers.nl',
    'password' => 'serverpass',
    'servicename' => 'Amservers Service Server',
    'network' => 'Amservers',
    'debug_channel' => '#twilightzone',
    'numeric' => 'Ag', // No need to change this.
    'debug' => true,
    'vhost' => 'users.amservers.nl',
    'helps' => '#help.script',
    'help' => '#help',
    'site' => 'www.amservers.nl',

    'ownhost' => 'x.x.x.x' // currently not doing anything - you might want to figure out which ip the service is using for connecting, or adding multiple Connect { lines with all ips on your server.. (something to work on)
);


$bots['ms'] = array(
    'servicename' => 'Operservice 0.1',
    'snumeric' => $config['numeric'].'AAA',
    'nickname' => 'O2',
    'ident' => 'operserv',
    'botmodes' => '+kdo',
    'vhost' => 'operserv.amservers.nl',
);

$bots['c'] = array(
    'servicename' => 'Channel Service 0.5',
    'snumeric' => $config['numeric'].'AAQ',
    'nickname' => 'A',
    'ident' => 'A',
    'auth' => 'A',
    'botmodes' => '+kdr A',
    'vhost' => 'Service.amservers.nl'
);

$bots['s'] = array(
    'servicename' => 'Version Stats 0.1',
    'snumeric' => $config['numeric'].'AAS',
    'nickname' => 'V',
    'ident' => 'Versi',
    'botmodes' => '+kd',
    'vhost' => 'n'
);


$bots['g'] = array(
    'servicename' => 'HelpBot 0.1',
    'snumeric' => $config['numeric'].'AAG',
    'nickname' => 'G',
    'ident' => 'help',
    'auth' => 'G',
    'botmodes' => '+okr G',
    'vhost' => 'amservers.nl'
);

$bots['r'] = array(
    'servicename' => 'Service Request 0.1',
    'snumeric' => $config['numeric'].'AAR',
    'nickname' => 'R',
    'ident' => 'Request',
    'auth' => 'R',
    'botmodes' => '+okr R',
    'vhost' => 'amservers.nl'
);

$bots['google'] = array(
    'servicename' => '/invite Google #channel',
    'snumeric' => $config['numeric'].'AAZ',
    'nickname' => 'Google',
    'ident' => 'G',
    'auth' => 'Google',
    'botmodes' => '+rX Google',
    'vhost' => '@gle'
);

?>
