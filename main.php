<?php

/**
 *
 * Welcome to the main file of the Q9 Services!
 * @author Arie - #Arie on QuakeNet!
 *
 */

require_once("config.php");
require_once("db.php");

$db = new dbAbstraction($mysql['type']);
$db->connect($mysql['user'], $mysql['pass'], $mysql['name']);

$main = new functions(&$db, &$config, &$bots);

class functions {

    // Basic connecting parts of this function, plus in the connectServices function have been made by some dude who made the PHP fishbot. Rest is made by #Arie on QuakeNet.
    public function __construct(&$db, &$config, &$bots) {
        $this->db = &$db;
        $this->config = &$config;
        $this->bots = &$bots;

        printf("PID: %s\n",posix_getpid());
        $this->Socket = @fsockopen($this->config['server'],$this->config['port'],$ErrNr,$ErrStr);
        if (!$this->Socket) {
                $tmp = sprintf("\n\nProblems with connecting to %s:%s\nErrorNr.: %s\nErrStr.: %s\n\n",$this->config['server'],$this->config['port'],$ErrNr,$ErrStr);
                die ($tmp);
        }

        $Time = time();
        $tmp = sprintf('PASS :%s',$this->config['password']);
        $this->SendRaw($tmp,1);
        $tmp = sprintf('SERVER %s 1 %s %s J10 %s]]] +s :%s',$this->config['cline'],$Time,$Time,$this->config['numeric'],$this->config['servicename']);
        $this->SendRaw($tmp,1);
            $this->loadCommands();
        	$this->getAuths();
        	$this->getChanlevs();
        	$this->gethelps();
        	$this->getWelcomes();
        $tmp = sprintf('%s EB',$this->config['numeric']);
        $this->SendRaw($tmp,1);
        $this->Counter =0;

		if ($this->config['debug']) {
            printf("Bot sended his own information to the server, waiting for respond.\n");
            @ob_flush();
        }

        $this->Idle();
    }

        private function connectServices() {
            if (is_array($this->bots)) {
                foreach ($this->bots as $moo => $mrr) {
                    if (empty($this->bots[$moo]['connected'])) {
                    $tmp = sprintf('%s N %s 1 %s %s %s %s B]AAAB %s :%s',$this->config['numeric'],$this->bots[$moo]['nickname'],time(),$this->bots[$moo]['ident'],$this->bots[$moo]['vhost'],$this->bots[$moo]['botmodes'],$this->bots[$moo]['snumeric'],$this->bots[$moo]['servicename']);
                    $this->SendRaw($tmp,1);
                    $this->bots[$moo]['connected'] = true;
                    $modes = explode(" ", $this->bots[$moo]['botmodes']);
                    if ($moo == 'google') $modes[0] = '+orX';

                    $auth = $this->bots[$moo]['auth'];
                    $num = $this->bots[$moo]['snumeric'];
                    $this->user[$this->bots[$moo]['snumeric']] = array(
                        'server' => $this->config['servicename'],
                        'nick' => $this->bots[$moo]['nickname'],
                        'numeric' => $this->bots[$moo]['snumeric'],
                        'ctime' => time(),
                        'ident' => $this->bots[$moo]['ident'],
                        'host' => $this->bots[$moo]['vhost'],
                        'modes' => $modes[0],
                        'auth' => $auth,
                    );
                    if ($auth) $this->auth[strtolower($auth)]['users'][] = $num;
                    $this->nick[strtolower($this->bots[$moo]['nickname'])] = $this->bots[$moo]['snumeric'];
                    }
                }
            }
            else print "Some error in the config!";
			$this->joinChannels();
			$this->getVersions();
        }

        private function Idle() {
        	/* Checking the incoming information */
        	while (!feof($this->Socket)) {
        		$this->Get = fgets($this->Socket,512);
        		if (!empty($this->Get)) {
        			$Args = explode(" ",$this->Get);
        			if ($Args[0] == 'SERVER') $this->saveMainServer($Args);
        			$Cmd = trim($Args[1]);
        			switch ($Cmd) {
        				case "EB": /* End of Burst */
        					$this->EA();
        					$this->serverEndB($Args);
        					break;
        				case "SQ":
        					$this->squitServer($Args);
        					break;
        				case "G": /* Ping .. Pong :) */
        					$this->Pong($Args);
        					break;
        				case "B":
        				    $this->saveChannels($Args);
        				    break;
        				case "S":
        				    $this->saveServer($Args);
        				    break;
        				case "N":
        				    $this->saveUsers($Args);
        				    break;
        				case "P":
        				    $this->pmParse($Args,$this->Get);
        				    break;
        				case "I":
        				    $this->invite($Args,$this->Get);
        				    break;
        				case "O":
        				    $this->noticeParse($Args,$this->Get);
        				    break;
        				case "M":
        				    $this->modeChange($Args);
        				    break;
        				case "OM":
        				    $this->modeChange($Args);
        				    break;
        				case "CM":
        				    $this->clearMode($Args);
        				    break;
        				case "J":
        				    $this->joinChan($Args);
        				    break;
        				case "K":
        				    $this->kickUser($Args);
        				    break;
        				case "C":
        				    $this->createChan($Args);
        				    break;
        				case "L":
        				    $this->leaveChan($Args);
        				    break;
        				case "Q":
        				    $this->quitUser($Args);
        				    break;
        				case "D":
        				    $this->killedUser($Args);
        				    break;
        				case "W":
        				    $this->sWhois($Args);
        				    break;
        				case "AC":
        				    $this->authUser($Args);
        				    break;
        			}
        		}
        	}
        }

        public $chan;
        public $user;
        public $nick;
        public $email;
        public $auth;
        public $chanlev;
        public $rchanlev;
        public $helps;
        public $server;
        public $schan;
        public $pchan;
        public $lchan;
        public $versions;
        public $versionallow;
        public $authflood;
        public $command;
        public $welcome;

        private function saveData($sender) {

            $auth = $this->getAuth($sender);

            if ($auth) {

                if (is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {

                    print_r($this->server);

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Saving chanlevs...', 1);
                    $this->db->query("
                        TRUNCATE TABLE chanlevs
                    ");
                    if (is_array($this->chanlev)) {
                        foreach ($this->chanlev as $moo => $mee) {
                            if (is_array($mee)) {
                                foreach (array_keys($mee) as $key) {
                                    $id = $this->auth[strtolower($key)]['id'];
                                    if ($id) {
                                        $this->db->query("
                                            INSERT INTO chanlevs
                                            VALUES ('$moo', '$id', '$mee[$key]')
                                        ");
                                    }
                                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :No id for '.$moo.' - '.$mee[$key], 1);
                                }
                            }
                            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: chanlevs is no array. #2', 1);
                        }
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: chanlevs is no array. #1', 1);


                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Saving helptopics...', 1);
                    $this->db->query("
                        TRUNCATE TABLE helps
                    ");
                    if (is_array($this->helps)) {
                        foreach ($this->helps as $tag => $answer) {
                            $tag = $this->db->escape($tag);
                            $answer = $this->db->escape($answer);
                            if ($tag && $answer) {
                                $this->db->query("
                                    INSERT INTO helps
                                    VALUES ($tag, $answer)
                                ");
                            }
                        }
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: helps is no array. #1', 1);

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Saving welcomes...', 1);
                    $this->db->query("
                        TRUNCATE TABLE welcomes
                    ");
                    if (is_array($this->welcome)) {
                        foreach ($this->welcome as $chan => $moo) {
                            if (is_array($moo)) {
                                foreach (array_keys($moo) as $service) {
                                    if ($chan && $moo && $service) {
                                        $chan2 = $this->db->escape($chan);
                                        $service2 = $this->db->escape($service);
                                        $message2 = $this->db->escape($moo[$service]);
                                        $this->db->query("
                                            INSERT INTO welcomes
                                            VALUES ($chan2, $service2, $message2)
                                        ");
                                    }
                                }
                            }
                            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: welcomes is no array. #2', 1);
                        }
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: welcomes is no array. #1', 1);

                    $this->helps[strtolower($row['tag'])] = $row['answer'];


                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Saving auths...', 1);
                    $this->db->query("
                        TRUNCATE TABLE auths
                    ");
                    if (is_array($this->auth)) {
                        foreach ($this->auth as $moo => $mee) {
                            $id = $this->db->escape($mee['id']);
                            if ($mee['password']) $pass = $this->db->escape($mee['password']);
                            else unset($pass);
                            $ctime = $this->db->escape($mee['ctime']);
                            $userflags = $this->db->escape($mee['userflags']);
                            $email = $this->db->escape($mee['email']);
                            if ($mee['name']) $name = $this->db->escape($mee['name']);
                            else unset($name);
                            if ($name && $pass) {
                                $this->db->query("
                                    INSERT INTO auths
                                    VALUES ($id, $name, $pass, $ctime, $userflags, $email)
                                ");
                            }
                        }
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: auths is no array. #1', 1);

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Saving channel information...', 1);
                    $this->db->query("
                        TRUNCATE TABLE channels
                    ");
                    if (is_array($this->schan)) {
                        foreach ($this->schan as $moo => $mee) {
                            $name = $this->db->escape($moo);
                            if (is_array($mee)) {
                                foreach (array_keys($mee) as $key) {
                                    $flags = $this->db->escape($this->schan[$moo][$key]['flags']);
                                    $topic = $this->db->escape($this->schan[$moo][$key]['topic']);
                                    $owner = $this->db->escape($key);
                                    $suspended = $this->db->escape($this->schan[$moo][$key]['suspended']);
                                    $autolimit = $this->db->escape($this->schan[$moo][$key]['autolimit']);
                                    $this->db->query("
                                        INSERT INTO channels
                                        VALUES ($name, $flags, $topic, $owner, $suspended, $autolimit)
                                    ");
                                }
                            }
                            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: channels is no array. #2', 1);
                        }
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: channels is no array. #1', 1);

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Saving versions...', 1);
                    $this->db->query("
                        TRUNCATE TABLE versions
                    ");
                    if (is_array($this->versions)) {
                        foreach ($this->versions as $moo => $mee) {
                            $ip = $this->db->escape($moo);
                            if (is_array($mee)) {
                                foreach (array_keys($mee) as $key) {
                                    $version = $this->db->escape($mee[$key]);
                                    $this->db->query("
                                        INSERT INTO versions
                                        VALUES ($ip, $version)
                                    ");
                                }
                            }
                            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: versions is no array. #2', 1);
                        }
                        $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Done.', 1);
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Error: versions is no array. #1', 1);
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use save.', 1);
            }
            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use save.', 1);
        }

        private function sWhois($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $bot = $this->botToName(str_replace(':', '', $args[3]));
            $bot = $this->numtoBot($bot);
            $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :More information about me can be found at www.netirc.eu.', 1);
        }

        private function authUser($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            // if the user is authed in a bugged way.. (like by old Qnet Q version ;-))
            if (is_numeric(strpos($args[3], ':'))) {
                for ($i = 0; $i <= strlen($args[3]); $i++) {
                    $cur = $args[3]{$i};
                    if ($cur == ':') break;
                    else $auth .= $cur;
                }
            }
            else $auth = $args[3];

            $this->user[$args[2]]['auth'] = $auth;

            $auth = strtolower($auth);

            $this->auth[$auth]['users'][] = $args[2];

            if (is_array($this->rchanlev[$auth])) {
                foreach ($this->rchanlev[$auth] as $chan => $flags) {
                    $curc = $flags;
                    if ($curc) {
                        if (is_numeric(strpos($curc, 'a'))) {
                            if (is_numeric(strpos($curc, 'o'))) {
                                if ($this->user[$args[2]]['channels'][$chan] == 'voiced') {
                                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +o '.$args[2], 1);
                                    $this->setStatus($args[2], $chan, 'opvoiced');
                                }
                                elseif ($this->user[$args[2]]['channels'][$chan] == 'regged') {
                                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +o '.$args[2], 1);
                                    $this->setStatus($args[2], $chan, 'opped');
                                }
                            }
                            elseif (is_numeric(strpos($curc, 'v'))) {
                                if ($this->user[$args[2]]['channels'][$chan] == 'regged') {
                                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +v '.$args[2], 1);
                                    $this->setStatus($args[2], $chan, 'voiced');
                                }
                            }
                        }
                        if (is_numeric(strpos($curc, 'i')))
                            $this->SendRaw($this->bots['c']['snumeric'].' I '.$this->user[$args[2]]['nick'].' '.$chan, 1);
                    }
                }
            }
        }

        private function inviteuser($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $channel = strtolower($args[4]);
            $auth = $this->getAuth($sender);
            if ($auth) {
                if ($channel) {
                    $curc = $this->chanlev[$channel][$auth];
                    if (is_numeric(strpos($curc, 'i')) || is_numeric(strpos($curc, 'o'))  || is_numeric(strpos($curc, 'm'))  || is_numeric(strpos($curc, 'n')) ) {
                        $this->SendRaw($this->bots['c']['snumeric'].' I '.$this->user[$sender]['nick'].' '.$channel, 1);
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done.', 1);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$channel.' to use invite.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' invite <#channel>', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :invite is only available to authed users.', 1);
        }

        private function getAuthsOfEmail($email) {
            if (is_array($this->email[$email])) {
                foreach ($this->email[$email] as $auth) {
                    $ret .=
'Auth: '.$auth.'
Password: '.$this->auth[strtolower($auth)]['password']."\n";
                }
                return $ret;
            }
        }

        private function reqpasswd($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $email = strtolower($args[4]);

            if ($email) {
                if ($this->email[$email]) {
                    $bericht =
'Hello '.$email.',

You or someone else requested the username and password for your e-mail adres. You can use the data below to login to '.$this->bots['c']['nickname'].'.

'.$this->getAuthsOfEmail($email).'

This message whas automaticaly generated by '.$this->bots['c']['nickname'].' on '.$this->config['network'].'.';
                    mail($email, 'Your '.$this->bots['c']['nickname'].' password', $bericht, 'From: "NetIRC" <Q@Service.NetIRC.eu>');
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :An e-mail has been send to '.$email.'.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :That e-mail adres is not matching any auth.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' requestpassword user@mymailhost.xx', 1);
        }

        private function op($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $channel = strtolower($args[4]);
            $auth = $this->getAuth($sender);

            if (trim($args[5])) {
                $moo = 'moo';
                unset($args[0]);
                unset($args[1]);
                unset($args[2]);
                unset($args[3]);
                unset($args[4]);
            }

            if ($auth) {
                if ($channel) {
                    if ($this->chanlev[$channel]) {
                        $curc = $this->chanlev[$channel][$auth];
                        if ($moo == 'moo') {
                            if ((is_numeric(strpos($curc, 'm')) || is_numeric(strpos($curc, 'n'))) || (is_numeric(strpos($this->getAuthlevel($auth), 'd')))) {
                                foreach ($args as $nick) {
                                    $user = $this->nick[strtolower($nick)];
                                    if ($this->chan[$channel]['users'][$user]) {
                                        if ($this->user[$user]['channels'][$channel] == 'voiced') {
                                            $this->setStatus($user, $channel, 'opvoiced');
                                            $opnicks[] = $user;
                                        }
                                        elseif ($this->user[$user]['channels'][$channel] == 'regged') {
                                            $this->setStatus($user, $channel, 'opped');
                                            $opnicks[] = $user;
                                        }
                                    }
                                }
                                if ($opnicks) {
                                    $count = count($opnicks);
                                    for ($i = 0,$y = 0; $i <= $count; $i++) {
                                        $som = $i / 6;
                                        if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                                            for ($x = 0; $x <= 5; $x++)
                                                unset($opnicks[$x]);
                                            $y++;
                                        }
                                        $nicks2op[$y] .= ' '.$opnicks[$i];
                                    }
                                    foreach ($nicks2op as $oprow) {
                                        $moo = explode(' ', $oprow);
                                        $count = count($moo);
                                        for ($i = 0; $i <= $count; $i++) {
                                            $oc .= 'o';
                                        }
                                        $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +'.$oc.' '.trim($oprow), 1);
                                        unset($oc);
                                    }
                                }
                                else {
                                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :No nick\'s to op. (Already opped or not in the channel)', 1);
                                    return;
                                }

                            }
                        }

                        elseif (is_numeric(strpos($curc, 'o')) || is_numeric(strpos($curc, 'm')) || is_numeric(strpos($curc, 'n')) || is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                            if ($this->user[$sender]['channels'][$channel] == 'voiced') {
                                $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +o '.$sender, 1);
                                $this->setStatus($sender, $channel, 'opvoiced');
                            }
                            elseif ($this->user[$sender]['channels'][$channel] == 'regged') {
                                $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +o '.$sender, 1);
                                $this->setStatus($sender, $channel, 'opped');
                            }
                        }
                        else {
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$channel.' to use op.', 1);
                            return;
                        }
                    }
                    else {
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$channel.' is unknown or suspended.', 1);
                        return;
                    }
                }

                else {
                    if (is_array($this->auth[$auth]['channels'])) {
                        foreach ($this->auth[$auth]['channels'] as $chan => $flags) {
                            if ($this->user[$sender]['channels'][$chan]) {
                                if (is_numeric(strpos($flags, 'o')) || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($flags, 'n'))) {
                                    if ($this->user[$sender]['channels'][$chan] == 'voiced') {
                                        $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +o '.$sender, 1);
                                        $this->setStatus($sender, $chan, 'opvoiced');
                                    }
                                    elseif ($this->user[$sender]['channels'][$chan] == 'regged') {
                                        $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +o '.$sender, 1);
                                        $this->setStatus($sender, $chan, 'opped');
                                    }
                                }
                            }
                        }
                    }
                }
                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :op is only available to authed users.', 1);
        }

        private function voice($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $channel = strtolower($args[4]);
            $auth = $this->getAuth($sender);

            if (trim($args[5])) {
                $moo = 'moo';
                unset($args[0]);
                unset($args[1]);
                unset($args[2]);
                unset($args[3]);
                unset($args[4]);
            }

            if ($auth) {
                if ($channel) {
                    if ($this->chanlev[$channel]) {
                        $curc = $this->chanlev[$channel][$auth];
                        if ($moo == 'moo') {
                            if ((is_numeric(strpos($curc, 'm')) || is_numeric(strpos($curc, 'n'))) || (is_numeric(strpos($this->getAuthlevel($auth), 'd')))) {
                                foreach ($args as $nick) {
                                    $user = $this->nick[strtolower($nick)];
                                    if ($this->chan[$channel]['users'][$user]) {
                                        if ($this->user[$user]['channels'][$channel] == 'opped') {
                                            $this->setStatus($user, $channel, 'opvoiced');
                                            $opnicks[] = $user;
                                        }
                                        elseif ($this->user[$user]['channels'][$channel] == 'regged') {
                                            $this->setStatus($user, $channel, 'voiced');
                                            $opnicks[] = $user;
                                        }
                                    }
                                }
                                if ($opnicks) {
                                    $count = count($opnicks);
                                    for ($i = 0,$y = 0; $i <= $count; $i++) {
                                        $som = $i / 6;
                                        if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                                            for ($x = 0; $x <= 5; $x++)
                                                unset($opnicks[$x]);
                                            $y++;
                                        }
                                        $nicks2op[$y] .= ' '.$opnicks[$i];
                                    }
                                    foreach ($nicks2op as $oprow) {
                                        $moo = explode(' ', $oprow);
                                        $count = count($moo);
                                        for ($i = 0; $i <= $count; $i++) {
                                            $oc .= 'v';
                                        }
                                        $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +'.$oc.' '.trim($oprow), 1);
                                        unset($oc);
                                    }
                                }
                                else {
                                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :No nick\'s to voice (Already voiced or not in the channel).', 1);
                                    return;
                                }

                            }
                        }

                        elseif (is_numeric(strpos($curc, 'o')) || is_numeric(strpos($curc, 'v')) || is_numeric(strpos($curc, 'm')) || is_numeric(strpos($curc, 'n')) || is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                            if ($this->user[$sender]['channels'][$channel] == 'opped') {
                                $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +v '.$sender, 1);
                                $this->setStatus($sender, $channel, 'opvoiced');
                            }
                            elseif ($this->user[$sender]['channels'][$channel] == 'regged') {
                                $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +v '.$sender, 1);
                                $this->setStatus($sender, $channel, 'voiced');
                            }
                        }
                        else {
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$channel.' to use voice.', 1);
                            return;
                        }
                    }
                    else {
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$channel.' is unknown or suspended.', 1);
                        return;
                    }
                }

                else {
                    foreach ($this->auth[$auth]['channels'] as $chan => $flags) {
                        if ($this->user[$sender]['channels'][$chan]) {
                            if (is_numeric(strpos($flags, 'v') || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($flags, 'n')))) {
                                if ($this->user[$sender]['channels'][$chan] == 'opped') {
                                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +v '.$sender, 1);
                                    $this->setStatus($sender, $chan, 'opvoiced');
                                }
                                elseif ($this->user[$sender]['channels'][$chan] == 'regged') {
                                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +v '.$sender, 1);
                                    $this->setStatus($sender, $chan, 'voiced');
                                }
                            }
                        }
                    }
                }
                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :op is only available to authed users.', 1);
        }


        private function setWelcome($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {
                $count = count($args);
                for ($i = 5; $i <= $count; $i++) {
                    $welcome .= ' '.$args[$i];
                }
                $welcome = trim($welcome);
                if ($chan) {

                    if ($this->schan[$chan]['c'] && !$this->schan[$chan]['c']['suspended']) {
                        $flags = $this->chanlev[$chan][$auth];
                        if (is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                            if ($welcome) {
                                $this->welcome[$chan]['c'] = $welcome;
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done. Welcome message for '.$chan.': '.$welcome, 1);
                            }
                            else {
                                $bericht = $this->getWelcome($chan, 'c');
                                if ($bericht) $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Welcome message for '.$chan.': '.$bericht, 1);
                                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :No message for '.$chan.'.', 1);
                            }
                        }
                        else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to use autolimit.', 1);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' welcome <#channel> [message]', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }


        private function autolimit($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {

            $setlimit = strtolower($args[5]);
            if ($chan) {

                if ($this->schan[$chan]['c'] && !$this->schan[$chan]['c']['suspended']) {
                    $flags = $this->chanlev[$chan][$auth];
                    if (is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                        if ($setlimit) {
                            for ($i = 0; $i <= strlen($setlimit); $i++) {
                                $cur = $setlimit{$i};
                                if (is_numeric($cur))
                                    $limit .= $cur;
                            }
                            if ($limit > 500) $limit = 500;
                            $this->schan[$chan]['c']['autolimit'] = $limit;
                            if (is_numeric(strpos($this->schan[$chan]['c']['flags'], 'c'))) $this->setLimit($chan);
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done.', 1);
                            if ($limit)
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Current autolimit setting on '.$chan.': '.$limit, 1);
                        }
                        else {
                            $limit = $this->schan[$chan]['c']['autolimit'];
                            if (!$limit) $limit = 'none';
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Current autolimit setting on '.$chan.': '.$limit, 1);
                        }
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to use autolimit.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' autolimit <#channel> [#increase]', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function whois($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $user = strtolower($args[4]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {
                if ($user) {
                    if (strpos($user, '#') === 0 && strlen($user) > 1) {
                        $len = strlen($user);
                        for ($i = 1; $i <= $len; $i++)
                            $ruser .= $user{$i};
                        $uauth = $this->auth[$ruser]['name'];
                    }
                    if (!$uauth) $wuser = $this->nick[$user];
                    if ($wuser || $uauth) {
                        if (!$uauth) $uauth = $this->user[$wuser]['auth'];
                        $lauth = strtolower($uauth);
                        if ($lauth) {
                            foreach ($this->auth[$lauth]['users'] as $uzer) {
                                $accusers .= ' '.$this->user[$uzer]['nick'];
                            }
                            $accusers = trim($accusers);
                            if (!$accusers) $accusers = '(none)';

                            if (strpos($args[4], '#') === 0) $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :-Information for account '.$uauth.'-----', 1);
                            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :-Information for user '.$user.' (using account '.$uauth.')-----', 1);
                            if ($sender == $wuser || $this->isOper($sender)) {
                                $chanlevs = $this->getComChanlevs($uauth, $uauth);
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :User ID        : '.$this->auth[$lauth]['id'], 1);
                                $this->whoisStaff($sender, $uauth);
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :User flags     : +'.$this->auth[$lauth]['userflags'], 1);
                                if ($accusers) $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Account users  : '.$accusers, 1);
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :User created   : '.$this->timetodate($this->auth[$lauth]['ctime']), 1);
                                if ($this->user[$wuser]) $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Last user@host : '.$this->user[$wuser]['ident'].'@'.$this->user[$wuser]['host'], 1);
                                if ($chanlevs) {
                                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel                       Flags', 1);
                                    foreach ($chanlevs as $chan => $status) {
                                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$status, 1);
                                    }
                                }
                            }
                            else {
                                $chanlevs = $this->getComChanlevs($uauth, $auth);
                                if ($accusers) $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Account users  : '.$accusers, 1);
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :User created   : '.$this->timetodate($this->auth[$lauth]['ctime']), 1);
                                $this->whoisStaff($sender, $uauth);
                                if ($chanlevs) {
                                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel                       Flags', 1);
                                    foreach ($chanlevs as $chan => $status) {
                                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$status, 1);
                                    }
                                }

                            }
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :End of list.', 1);
                        }
                        else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :User '.$user.' is not authed.', 1);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Unknown user '.$user.'.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' whois <user>', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function whoisStaff($sender, $uauth) {
            $level = $this->getAuthlevel($uauth);
            if (is_numeric(strpos($level, 'a')))
            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Network Staff  : IRC Administrator', 1);

            elseif (is_numeric(strpos($level, 'o')))
            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Network Staff  : IRC Operator', 1);

            elseif (is_numeric(strpos($level, 'h')))
            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Network Staff  : Helper', 1);

            if (is_numeric(strpos($level, 'd')))
            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Network Staff  : Developer', 1);
        }

        private function getComChanlevs($auth1, $auth2) {
            $rauth = strtolower($auth1);
            $rauth2 = strtolower($auth2);
            $spaces = '                               ';
            foreach ($this->rchanlev[$rauth] as $chan => $flags) {
                if ($this->chanlev[$chan][$rauth2]) {
                    $cur = substr_replace($spaces, $chan, 1, strlen($chan));
                    $cur .= '+'.$flags;
                    $ret[$chan] = $cur;
                }
            }
            return $ret;
        }

        private function requestspamscan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            $bot = 'r';
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if (!$this->nick['s']) {
                $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :S is currently offline. Try again later.', 1);
                return;
            }

            if ($auth) {
                if ($chan) {
                    if (!$this->user[$this->nick['s']]['channels'][$chan]) {
                        if ($this->chan[$chan]) {
                            $status = $this->user[$sender]['channels'][$chan];
                            if ($status == 'opped' || $status == 'opvoiced') {
                                if ($this->schan[$chan]['c'] || $this->user[$this->nick['l']]['channels'][$chan]) {
                                    $ctime = $this->chan[$chan]['cdate'];
                                    $verschil =  time() - $ctime;
                                    if ($verschil >= 43200) {
                                        if (count($this->chan[$chan]['users']) >= 18) {
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Requirements met, S added.', 1);
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$this->nick['s'].' :join '.$chan, 1);
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$this->config['debug_channel'].' :Added S to '.$chan, 1);
                                        }
                                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not meet the requirements to request S. (see somefaqurl)', 1);
                                    }
                                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Your channel is too new. Try again later.', 1);
                                }
                                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :There is no channel service on that channel.', 1);
                            }
                            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You must be op\'d on the channel to request a service.', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :No such channel.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :S is already on that channel.', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' spamscan <#channel>', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }
        private function requestbot($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            $bot = 'r';
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {
                if ($chan) {
                    $susp = $this->schan[$chan]['c']['suspended'];
                    if (!$susp) {
                        if (!$this->schan[$chan]['c']) {
                            if ($this->chan[$chan]) {
                                $status = $this->user[$sender]['channels'][$chan];
                                if ($status == 'opped' || $status == 'opvoiced') {
                                    $ctime = $this->chan[$chan]['cdate'];
                                    $verschil =  time() - $ctime;
                                    if ($verschil >= 300) {
                                        if (count($this->chan[$chan]['users']) >= 7) {
                                            $this->joinService($chan, 'c');
                                            $this->chanlev[$chan][$auth] = 'amnov';
                                            $this->rchanlev[$auth][$chan] = 'amnov';
                                            $this->schan[$chan]['c']['flags'] = 'j';
                                            $this->schan[$chan]['c']['moo'] = 'mo';
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Requirements met, '.$this->bots['c']['nickname'].' added.', 1);
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$this->nick['l'].' :delchan '.$chan, 1);
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$this->config['debug_channel'].' :Added '.$this->bots['c']['nickname'].' to '.$chan, 1);
                                        }
                                        elseif (count($this->chan[$chan]['users']) >= 4) {
                                            if ($this->user[$this->nick['l']]['channels'][$chan]) {
                                                $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not meet the requirements to request '.$this->bots['c']['nickname'].' and you have L already. (see somefaqurl)', 1);
                                                return;
                                            }
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$this->nick['l'].' :addchan '.$chan.' #'.$auth, 1);
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Requirements met, L added.', 1);
                                            $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$this->config['debug_channel'].' :Added L to '.$chan, 1);
                                        }
                                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not meet the requirements to request '.$this->bots['c']['nickname'].' or L. (see somefaqurl)', 1);
                                    }
                                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Your channel is too new. Try again later.', 1);
                                }
                                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You must be op\'d on the channel to request a service.', 1);
                            }
                            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :No such channel.', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :'.$this->bots['c']['nickname'].' is already on that channel.', 1);
                    }
                    else {
                        $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Error: You are not allowed to request a service to this channel.', 1);
                        $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Reason: '.$susp, 1);
                    }
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' requestbot <#channel>', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function bandel($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {

            $bandel = strtolower($args[5]);
            if ($chan) {

                if ($this->schan[$chan]['c'] && !$this->schan[$chan]['c']['suspended']) {
                    $flags = $this->chanlev[$chan][$auth];
                    if (is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                        if ($bandel) {
                            if ($this->chan[$chan]['bans']) {
                                foreach ($this->chan[$chan]['bans'] as $bnr => $ban) {
                                    if ($ban == $bandel) {
                                        $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' -b '.$ban, 1);
                                        unset($this->chan[$chan]['bans'][$bnr]);
                                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done.', 1);
                                        return;
                                    }
                                }
                            }
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Can\'t find ban '.$bandel.' on '.$chan.'.', 1);
                        }
                        else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' bandel <#channel> <ban>', 1);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to use bandel.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' bandel <#channel> <ban>', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function newpass($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $auth = $this->getAuth($args[0]);

            $oldpass = $args[4];
            $newpass1 = $args[5];
            $newpass2 = $args[6];
            if ($auth) {
                if ($oldpass && $newpass1 && $newpass2) {
                    if ($oldpass == $this->auth[$auth]['password']) {
                        if ($newpass1 == $newpass2) {
                            if (strlen($newpass1) > 2) {
                                $this->auth[$auth]['password'] = $newpass1;
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done. Password saved.', 1);
                            }
                            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :New password to short.', 1);
                        }
                        else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :New passwords do not match.', 1);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Incorrect password.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' newpass <oldpassword> <newpassword> <newpassword>', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function listauths($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            if ($this->isOper($sender)) {
                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :I have '.count($this->auth).' auths in my database.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access to use users.', 1);
        }

        private function banlist($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {

            if ($chan) {

                if ($this->schan[$chan]['c'] && !$this->schan[$chan]['c']['suspended']) {
                    $flags = $this->chanlev[$chan][$auth];
                    if (is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                        if (count($this->chan[$chan]['bans']) >= 1) {
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Registered bans on '.$chan.':', 1);
                            $i = 1;
                            foreach ($this->chan[$chan]['bans'] as $m00 => $test) {
                                if ($test) {
                                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :#'.$i.' '.$test, 1);
                                    $i++;
                                }
                            }
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :End of list.', 1);
                        }
                        else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :No bans on '.$chan.'.', 1);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to use banlist.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' banlist <#channel>', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function deopall($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {

            if ($chan) {

                if ($this->schan[$chan]['c'] && !$this->schan[$chan]['c']['suspended']) {
                    $flags = $this->chanlev[$chan][$auth];
                    if (is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                        //$this->deopusers($chan);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to use deopall.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' deopall <#channel>', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function banclear($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {

            if ($chan) {

                if ($this->schan[$chan]['c'] && !$this->schan[$chan]['c']['suspended']) {
                    $flags = $this->chanlev[$chan][$auth];
                    if (is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'm')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                        if (!$limit) $limit = 'none';
                        $this->SendRaw($this->config['numeric'].' CM '.$chan.' b', 1);
                        $count = count($this->chan[$chan]['bans']);
                        unset($this->chan[$chan]['bans']);
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$count.' bans removed from channel.', 1);
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to use banclear.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' banclear <#channel>', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }


        private function serverEndB($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            //print_r($args);

            foreach ($this->server as $server => $moo) {
                if ($server == $args[0]) {
                    $this->connectServices();
                    if (is_array($moo)){
                        foreach (array_keys($moo) as $key) {
                            $this->server[$server][$key]['eb'] = 'eb';
                        }
                    }
                }
                else {
                    foreach (array_keys($moo) as $key) {
                        if ($key == $args[0])
                            $this->server[$server][$key]['eb'] = 'eb';
                    }
                }
            }
        }

        private function squitServer($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            foreach ($this->server as $server => $moo) {
                if (is_array($moo)){
                    foreach (array_keys($moo) as $key) {
                        if ($this->server[$server][$key]['name'] == $args[2]) {
                            $i = 0;
                            foreach ($this->user as $user => $info) {
                                if ($this->user[$user]['server'] == $key) {
                                    //$this->SendRaw($this->bots['ms']['snumeric'].' P '.$this->config['debug_channel'].' :User: '.$this->user[$user]['nick'].' ('.$user.')', 1);
                                    unset($this->nick[strtolower($this->user[$user]['nick'])]);
                                    $this->removeUserChannels($user);
                                    unset($this->user[$user]);
                                    $i++;
                                }
                            }
                            if ($i) $xtra = ' ['.$i.'] users gone.';
                            $this->SendRaw($this->bots['ms']['snumeric'].' P '.$this->config['debug_channel'].' :server ['.$this->server[$server][$key]['name'].'] squitted.'.$xtra, 1);

                            $totaal += $i;

                            if (is_array($this->server[$key])) {
                                print_r($this->server[$key]);
                                $i = 0;
                                foreach (array_keys($this->server[$key]) as $snum) {
                                    foreach ($this->user as $user => $info) {
                                        if ($this->user[$user]['server'] == $snum) {
                                            $this->SendRaw($this->bots['ms']['snumeric'].' P '.$this->config['debug_channel'].' :User: '.$this->user[$user]['nick'].' ('.$user.')', 1);
                                            unset($this->nick[strtolower($this->user[$user]['nick'])]);
                                            $this->removeUserChannels($user);
                                            unset($this->user[$user]);
                                            $i++;
                                        }
                                    }
                                    $totaal += $i;
                                    if ($i) $xtra = ' ['.$i.'] users gone.';
                                    if ($totaal > $i) $xtra = ' ['.$i.'] users gone, total: '.$totaal;
                                    $this->SendRaw($this->bots['ms']['snumeric'].' P '.$this->config['debug_channel'].' :server ['.$this->server[$server][$key]['name'].'] squitted.'.$xtra, 1);
                                }
                            }
                        }
                    }
                }
            }
        }
        private function saveServer($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $snum = $args[0];
            $name = $args[2];
            $hop = $args[3];
            $start = $args[4];
            $link = $args[5];
            $prot = $args[6];
            $num = $args[7];
            $mode = $args[8];

            for ($i = 0; $i <= 1; $i++) {
                $ownnum .= $args[7]{$i};
            }

            unset($args[0]);
            unset($args[1]);
            unset($args[2]);
            unset($args[3]);
            unset($args[4]);
            unset($args[5]);
            unset($args[6]);
            unset($args[7]);
            unset($args[8]);

            foreach ($args as $arg) {
                $dec .= ' '.$arg;
            }
            $dec = trim($dec);
            for ($i = 1; $i <= strlen($dec); $i++) {
                $descr .= $dec{$i};
            }

            if (!$snum) $snum = $ownnum;

            $this->server[$snum][$ownnum] = array(
                'name' => $name,
                'hop' => $hop,
                'start' => $start,
                'link' => $link,
                'prot' => $prot,
                'num' => $num,
                'mode' => $mode,
                'descr' => $descr
            );
        }

        private function saveMainServer($argz) {

            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            for ($i = 0; $i <= 1; $i++) {
                $tot .= $args[6]{$i};
            }

            $argb[0] = $tot;
            $argb[1] = 'S';

            $i = 0;
            foreach ($args as $arg) {
                $o = $i - 1;
                $argb[$i] = $args[$o];
                $i++;
            }
            $argb[11] = $args[10];

            $this->saveServer($argb);
        }

        private function quitUser($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $this->removeUserChannels($args[0]);

            $nick = $args[0];
            unset($args[0]);
            unset($args[1]);
            foreach ($args as $arg) {
                $reason .= ' '.$arg;
            }
            $reason = trim($reason);
            for ($i = 1; $i <= strlen($reason); $i++) {
                $treason .= $reason{$i};
            }

            $this->SendRaw($this->bots['ms']['snumeric'].' P '.$this->config['debug_channel'].' :user ['.$this->user[$nick]['nick'].'] has quitted ['.$treason.']', 1);
            unset($this->user[$nick]['nick']);
            $this->removeUserChannels($nick);
            unset($this->user[$nick]);
        }

        private function killedUser($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            foreach ($this->bots as $moo => $mrr) {
                if ($args[2] == $mrr['snumeric']) {
                    $this->SendRaw($this->config['numeric'].' P '.$this->config['debug_channel'].' :Service '.$mrr['nickname'].' has been killed by '.$this->user[$args[0]]['nick'].'.. connecting the service aganin.', 1);
                    $this->bots[$moo]['connected'] = false;
                    $this->connectServices();
                    $nvm = true;
                }
            }
            if (!$nvm) {
                $nick = $args[0];
                $victim = $args[2];

                unset($args[0]);
                unset($args[1]);
                unset($args[2]);
                unset($args[3]);

                foreach ($args as $arg) {
                    $reason .= ' '.$arg;
                }

                $tmp = $this->Return_Substrings($reason, '(', ')');
                $killreason = ':Killed: ['.$tmp[0].'] by ['.$this->user[$nick]['nick'].']';
                $sending[] = $victim;
                $sending[] = 'Q';
                $sending[] = $killreason;
                $this->quitUser($sending);
            }

            //if ($args[2] == )
        }

        private function kickUser($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $this->removeUserChannels($args[3], $args[2]);

            $bot = $this->numtoBot($args[3]);
            if ($bot) {
                $this->SendRaw($this->bots[$bot]['snumeric'].' L '.$args[2], 0);
                if ($bot == 'google') {
                    $this->leaveService($bot, $args[2]);
                    unset($this->schan[strtolower($args[2])][$bot]);
                    return;
                }
                $this->joinService($args[2], $bot);
            }

        }

        private function joinChan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            if (is_numeric(strpos($args[2], ','))) {
                $moo = explode(',', $args[2]);
                foreach ($moo as $channel) {
                    $mee[] = $args[0];
                    $mee[] = $args[1];
                    $mee[] = $channel;
                    $mee[] = $args[3];
                    $this->joinChan2($mee);
                    unset($mee);
                }
            }
            else $this->joinChan2($argz);
        }

        private function createChan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            if (is_numeric(strpos($args[2], ','))) {
                $moo = explode(',', $args[2]);
                print_r($moo);
                print_r($args[2]);
                foreach ($moo as $channel) {
                    $mee[] = $args[0];
                    $mee[] = $args[1];
                    $mee[] = $channel;
                    $mee[] = $args[3];
                    $this->createChan2($mee);
                    unset($mee);
                }
            }
            else $this->createChan2($argz);
        }

        private function joinChan2($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $channel = strtolower($args[2]);
            $sender = $args[0];

            if ($channel == '0') {
                $this->removeUserChannels($args[0]);
                unset($this->user[$args[0]]['channels']);
                return;
            }

            if ($this->schan[$channel]['c'] && !isset($this->schan[$channel]['c']['suspended'])) {

                if (is_numeric(strpos($this->schan[$channel]['c']['flags'], 'e'))) {
                    if ($this->kickMatched($channel, $sender) == true) return;
                }


                $auth = $this->getAuth($args[0]);
                $flags = $this->chanlev[$channel][$auth];
                if (is_numeric(strpos($this->schan[$channel]['c']['flags'], 'k')) && !$flags && !$this->isOper($sender) && !is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {

                    if ($this->user[$args[0]]['auth']) $ban = '*!*@'.$this->user[$args[0]]['auth'].'.'.$this->config['vhost'];
                    else $ban = '*!'.$this->user[$args[0]]['ident'].'@'.$this->user[$args[0]]['host'];

                    $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +b '.$ban, 1);

                    $this->chan[$channel]['bans'] [] = $ban;

                    $this->SendRaw($this->modek($channel, 'c').' K '.$channel.' '.$args[0].' :Authorised users only.', 1);
                    return;
                }


                if ($flags) {
                    if (is_numeric(strpos($flags, 'b')) && !$this->isOper($sender) && !is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                        $ban = '*!*@'.$this->user[$args[0]]['auth'].'.'.$this->config['vhost'];
                        $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +b '.$ban, 1);
                        $this->SendRaw($this->modek($channel, 'c').' K '.$channel.' '.$args[0].' :Banned.', 1);
                        $this->chan[$channel]['bans'][] = $ban;
                        return;
                    }
                    elseif (is_numeric(strpos($flags, 'a'))) {
                        if (is_numeric(strpos($flags, 'o'))) {
                            $this->setStatus($sender, $channel, 'opped');
                            $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +o '.$args[0], 1);
                        }
                        elseif (is_numeric(strpos($flags, 'v'))) {
                            $this->setStatus($sender, $channel, 'voiced');
                            $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +v '.$args[0], 1);
                        }
                    }
                }
                if (!$this->user[$args[0]]['channels'][$channel]) {
                    if (is_numeric(strpos($this->schan[$channel]['c']['flags'], 'v')) && $this->user[$args[0]]['channels'][$channel] != 'opped') {
                            $this->setStatus($sender, $channel, 'voiced');
                            $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +v '.$args[0], 1);
                    }
                    else $this->setStatus($sender, $channel, 'regged');
                }
            }
            else $this->setStatus($sender, $channel, 'regged');


            $this->welcomeuser($channel, $sender, 'c');
            if ($this->isGchan($channel)) $this->welcomeuser($channel, $sender, 'g');
        }

        private function welcomeuser($chan, $sender, $service) {
            if ($service == 'c') {
                if (!$this->schan[$channel][$service]['suspended'] && is_numeric(strpos($this->schan[$chan]['c']['flags'], 'w'))) {
                    $bericht = $this->welcome[$chan][$service];
                    if (strlen($bericht) > 0) $this->SendRaw($this->bots[$service]['snumeric'].' O '.$sender.' :['.$chan.'] '.$bericht, 1);
                }
            }
            else {
                if (!$this->schan[$channel][$service]['suspended'] && $this->schan[$channel][$service]) {
                    $bericht = $this->welcome[$chan][$service];
                    if (strlen($bericht) > 0) $this->SendRaw($this->bots[$service]['snumeric'].' O '.$sender.' :['.$chan.'] '.$bericht, 1);
                }
            }
        }

        private function getWelcome($chan, $service) {
            $bericht = $this->welcome[$chan][$service];
            if ($bericht) return $bericht;
            else return false;
        }

        private function createChan2($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $channel = strtolower($args[2]);

            $auth = $this->getAuth($args[0]);
            $flags = $this->chanlev[$channel][$auth];

            if (is_numeric(strpos($this->schan[$channel]['c']['flags'], 'k')) && !$flags && !is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                $this->joinService($channel, 'c');
                $this->pchan[$channel] = time();


                if ($this->user[$args[0]]['auth']) $ban = '*!*@'.$this->user[$args[0]]['auth'].'.'.$this->config['vhost'];
                else $ban = '*!'.$this->user[$args[0]]['ident'].'@'.$this->user[$args[0]]['host'];

                $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +b '.$ban, 1);

                $this->chan[$channel]['bans'] [] = $ban;

                $this->SendRaw($this->modek($channel, 'c').' K '.$channel.' '.$args[0].' :Authorised users only.', 1);
                return;
            }


            if ($flags) {
                if (is_numeric(strpos($flags, 'b'))) {
                    $this->joinService($channel, 'c');
                    $this->pchan[$channel] = time();

                    $ban = $this->user[$args[0]]['auth'].'.'.$this->config['vhost'];
                    $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' +b '.$ban, 1);
                    $this->SendRaw($this->modek($channel, 'c').' K '.$channel.' '.$args[0].' :Banned.', 1);
                    $this->chan[$channel]['bans'][] = $ban;
                    return;
                }
                elseif (is_numeric(strpos($flags, 'o')) || is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'm'))) {
                    $this->setStatus($args[0], $channel, 'opped');
                    if (is_numeric(strpos($this->schan[$channel]['c']['flags'], 'j'))) $this->joinService($channel, 'c');
                }
                elseif (is_numeric(strpos($flags, 'v'))) {
                    $this->joinService($channel, 'c');
                    $this->pchan[$channel] = time();

                    $this->setStatus($args[0], $channel, 'voiced');
                    $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' -o+v '.$args[0].' '.$args[0], 1);
                }
            }
            else {
                if (is_numeric(strpos($this->schan[$channel]['c']['flags'], 'v')) && $this->user[$args[0]]['channels'][$channel] != 'opped') {
                        $this->joinService($channel, 'c');
                        $this->pchan[$channel] = time();

                        $this->setStatus($args[0], $channel, 'voiced');
                        $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' -o+v '.$args[0].' '.$args[0], 1);
                }
                elseif (!$this->user[$args[0]]['channels'][$channel] && $this->schan[$channel]) {
                    $this->joinService($channel, 'c');
                    $this->pchan[$channel] = time();

                    $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' -o '.$args[0], 1);
                    $this->setStatus($args[0], $channel, 'regged');
                }
                else $this->setStatus($args[0], $channel, 'opped');
            }
            $this->chan[$channel]['cdate'] = $args[3];
        }

        private function modek($channel, $service) {
            $numeric = $this->bots[$service]['snumeric'];
            if ($this->user[$numeric]['channels'][$channel])
                return $this->bots[$service]['snumeric'];
            else return $this->config['numeric'];
        }

        private function leaveChan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $channel = strtolower($args[2]);
            if ($this->user[$args[0]]['channels'][$channel]) {
                $this->removeUserChannels($args[0], $channel);
                unset($this->user[$args[0]]['channels'][$channel]);
            }

            if (is_array($this->chan[$channel]['users'])) {
                foreach ($this->chan[$channel]['users'] as $user => $status) {
                    if ($status) $moo++;
                }
                if ($moo < 1) {
                    unset($this->chan[$channel]);
                }
            }
            else {
                unset($this->chan[$channel]);
                //print_r($this->chan[$channel]);
                //print "\n1: $channel\n";
            }
            //print "\n2: $channel\n";
        }

        private function clearMode($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $channel = strtolower($args[2]);

            for ($i = 0; $i < strlen($args[3]); $i++) {
                $cur = $args[3]{$i};
                if ($cur == 'o') {
                    foreach ($this->chan[$channel]['users'] as $user => $status) {
                        $mayb = $this->numtoBot($user);
                        $mayb2 = $this->bots[$mayb]['snumeric'];
                        if ($user == $mayb2) {
                            $deopusers[] = $mayb2;
                            $this->setStatus($mayb2, $channel, 'opped');
                        }
                        else {
                            if ($status == 'opped') {
                                $this->setStatus($user, $channel, 'regged');
                            }
                            if ($status == 'opvoiced')
                                $this->setStatus($user, $channel, 'voiced');
                        }
                    }
                    if (is_array($deopusers)) {
                        $count = count($deopusers);
                        for ($a = 0,$y = 0; $a <= $count; $a++) {
                            $som = $a / 6;
                            if ($a !== 0 && !is_numeric(strpos($som, '.'))) {
                                for ($x = 0; $x <= 5; $x++)
                                    unset($deopusers[$x]);
                                $y++;
                            }
                            $nicks2op[$y] .= ' '.$deopusers[$a];
                        }
                        foreach ($nicks2op as $oprow) {
                            $moo = trim($oprow);
                            $moo = explode(' ', $oprow);
                            $count = count($moo);
                            for ($a = 1; $a < $count; $a++) {
                                $oc .= 'o';
                            }
                            $this->SendRaw($this->config['numeric'].' M '.$channel.' +'.$oc.' '.trim($oprow), 1);
                            unset($oc);
                        }
                    }
                    if (!$this->schan[$channel]['c']['suspended'] && is_numeric(strpos($this->schan[$channel]['c']['flags'], 'p'))) {
                        $this->opAllC($channel);
                    }
                }
                if ($cur == 'v') {
                    foreach ($this->chan[$channel]['users'] as $user => $status) {
                        if ($status == 'voiced') {
                            $this->setStatus($user, $channel, 'regged');
                        }
                        if ($status == 'opvoiced')
                            $this->setStatus($user, $channel, 'opped');
                    }
                }
                if ($cur == 'p') {
                    $this->chan[$channel]['modes'] = str_replace('p', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'n') {
                    $this->chan[$channel]['modes'] = str_replace('n', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 't') {
                    $this->chan[$channel]['modes'] = str_replace('t', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'r') {
                    $this->chan[$channel]['modes'] = str_replace('r', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'D') {
                    $this->chan[$channel]['modes'] = str_replace('D', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'd') {
                    $this->chan[$channel]['modes'] = str_replace('d', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'c') {
                    $this->chan[$channel]['modes'] = str_replace('c', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'C') {
                    $this->chan[$channel]['modes'] = str_replace('C', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'N') {
                    $this->chan[$channel]['modes'] = str_replace('N', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'u') {
                    $this->chan[$channel]['modes'] = str_replace('u', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 's') {
                    $this->chan[$channel]['modes'] = str_replace('s', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'm') {
                    $this->chan[$channel]['modes'] = str_replace('m', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'i') {
                    $this->chan[$channel]['modes'] = str_replace('i', '', $this->chan[$channel]['modes']);
                }
                if ($cur == 'k') {
                    $this->chan[$channel]['modes'] = str_replace('k', '', $this->chan[$channel]['modes']);
                    $this->chan[$channel]['key'] = '';
                }
                if ($cur == 'b') {
                    unset($this->chan[$channel]['bans']);
                }
                if ($cur == 'l') {
                    $this->chan[$channel]['modes'] = str_replace('l', '', $this->chan[$channel]['modes']);
                    $this->chan[$channel]['limit'] = '';
                }
            }

        }

        private function setStatus($numeric, $channel, $status) {
            //print "n: $numeric | c: $channel | s: $status\n";
            $this->chan[$channel]['users'][$numeric] = $status;
            $this->user[$numeric]['channels'][$channel] = $status;
        }

        private function removeUserChannels($numeric, $channel = false) {
            if ($channel) {
                unset($this->user[$numeric]['channels'][$channel]);
                unset($this->chan[$channel]['users'][$numeric]);
                $sends[] = $numeric;
                $sends[] = 'L';
                $sends[] = $channel;
                $this->leaveChan($sends);
            }
            else {
                if (is_array($this->user[$numeric]['channels'])) {
                    foreach ($this->user[$numeric]['channels'] as $chan => $status) {
                        unset($this->user[$numeric]['channels'][$chan]);
                        unset($this->chan[$chan]['users'][$numeric]);
                        $sends[] = $numeric;
                        $sends[] = 'L';
                        $sends[] = $chan;
                        $this->leaveChan($sends);
                        unset($sends);
                    }
                }
            }
        }

        private function modeChange($argz) {

            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $channel = strtolower($args[2]);

            if (strpos($channel, '#') === 0) {
                $tel = 3;
                for ($i = 0; $i <= strlen($args[3]); $i++) {
                    $cur = $args[3]{$i};
                    //print "cur: $cur\n";

                    if ($cur == '-') $mode = $cur;
                    if ($cur == '+') $mode = $cur;

                    if ($cur == 'l') {
                        if ($mode == '+') {
                            $tel++;
                            $this->chan[$channel]['limit'] = $args[$tel];
                        }
                        elseif ($mode == '-') $this->chan[$channel]['limit'] = '';
                    }
                    if ($cur == 'k') {
                        $tel++;
                        if ($mode == '+') $this->chan[$channel]['key'] = $args[$tel];
                        elseif ($mode == '-') $this->chan[$channel]['key'] = '';
                    }
                    if ($cur == 'o') {
                        $tel++;
                        if ($mode == '+') {
                            if (!$this->schan[$channel]['c']['suspended'] && !$this->isOper($args[0]) && is_numeric(strpos($this->schan[$channel]['c']['flags'], 'b'))) {
                                if (!$this->isOper($args[$tel])) {
                                    $auth = $this->user[$args[$tel]]['auth'];
                                    if (!is_numeric(strpos($chanlev, 'o')) || !is_numeric(strpos($chanlev, 'm')) || !is_numeric(strpos($chanlev, 'n'))) {
                                        $dops .= ' '.$args[$tel];
                                    }
                                }
                            }
                            else {
                                if ($this->user[$args[$tel]]['channels'][$channel] == 'voiced')
                                    $this->setStatus($args[$tel], $channel, 'opvoiced');

                                else $this->setStatus($args[$tel], $channel, 'opped');
                            }
                        }
                        elseif ($mode == '-') {
                            foreach ($this->bots as $moo => $mrr) {
                                if ($this->bots[$moo]['snumeric'] == $args[$tel]) {
                                    $this->SendRaw($this->config['numeric'].' M '.$channel.' +o '.$this->bots[$moo]['snumeric'], 1);
                                    $halt = 'moo';
                                }
                            }
                            if (!$halt) {
                                if (!$this->schan[$channel]['c']['suspended'] && is_numeric(strpos($this->schan[$channel]['c']['flags'], 'p'))) {
                                    $auth = $this->user[$args[$tel]]['auth'];
                                    $chanlev = $this->chanlev[$channel][strtolower($auth)];
                                    if (is_numeric(strpos($chanlev, 'o')) || is_numeric(strpos($chanlev, 'm')) || is_numeric(strpos($chanlev, 'n'))) {
                                        $ops .= ' '.$args[$tel];
                                    }
                                    else {
                                        if ($this->user[$args[$tel]]['channels'][$channel] == 'opvoiced')
                                            $this->setStatus($args[$tel], $channel, 'voiced');
                                        else $this->setStatus($args[$tel], $channel, 'regged');
                                    }
                                }
                                else {
                                    if ($this->user[$args[$tel]]['channels'][$channel] == 'opvoiced')
                                        $this->setStatus($args[$tel], $channel, 'voiced');
                                    else $this->setStatus($args[$tel], $channel, 'regged');
                                }
                            }
                        }
                    }
                    if ($cur == 'v') {
                        $tel++;
                        if ($mode == '+') {
                            if ($this->user[$args[$tel]]['channels'][$channel] == 'opped')
                                $this->setStatus($args[$tel], $channel, 'opvoiced');

                            else $this->setStatus($args[$tel], $channel, 'voiced');
                        }
                        elseif ($mode == '-') {
                            if (empty($this->user[$args[$tel]]['channels'][$channel]) == 'opped')
                                $this->setStatus($args[$tel], $channel, 'opped');

                            else $this->setStatus($args[$tel], $channel, 'regged');
                        }
                    }

                    if ($cur == 'b') {
                        $tel++;
                        if ($mode == '+') {
                            $this->chan[$channel]['bans'][] = $args[$tel];
                            if (is_numeric(strpos($this->schan[$channel]['c']['flags'], 'e')))
                                $this->kickBanneds($channel, $args[$tel]);
                        }

                        if ($mode == '-') {
                            foreach ($this->chan[$channel]['bans'] as $m00 => $test) {
                                if ($test == $args[$tel]) {
                                    unset($this->chan[$channel]['bans'][$m00]);
                                }
                            }
                        }
                    }
                    else {
                        $moden = array('i','m','n','p','s','t','r','D','d','c','C','N','u');

                        foreach ($moden as $m00 => $moo) {
                            if ($mode == '-')
                                if ($moo == $cur) $this->chan[$channel]['modes'] = str_replace($moo, '', $this->chan[$channel]['modes']);

                            if ($mode == '+')
                                if ($moo == $cur) $this->chan[$channel]['modes'] .= $moo;
                        }
                    }
                    unset($moo);
                }
                if ($dops) {
                    $dops = trim($dops);
                    $dops2 = explode(' ', $dops);
                    $aantal = count($dops2);
                    for ($i = 0; $i < $aantal; $i++) {
                        $dos .= 'o';
                    }
                }
                if ($ops) {
                    $ops = trim($ops);
                    $ops2 = explode(' ', $ops);
                    $aantal = count($ops2);
                    for ($i = 0; $i < $aantal; $i++) {
                        $os .= 'o';
                    }
                }
                if ($ops || $dops) {
                    $this->SendRaw($this->modek($channel, 'c').' M '.$channel.' -'.$dos.'+'.$os.' '.$dops.' '.$ops, 1);
                    unset($dops);
                    unset($ops);
                    unset($dos);
                    unset($os);
                }
            }

            else {

                $user = $args[2];
                $tel = 3;
                $m00 = $this->user[$this->nick[strtolower($user)]];
                for ($i = 0; $i <= strlen($args[3]); $i++) {
                    $cur = $args[3]{$i};

                    if ($cur == '-') $mode = $cur;
                    if ($cur == '+') $mode = $cur;

                    $moden = array('i','o','w','g','k','x','n','I','r','X','R');

                    foreach ($moden as $m33 => $moo) {
                        if ($moo == $cur) {
                            if ($mode == '+')
                                $this->user[$m00['numeric']]['modes'] .= $cur;

                            elseif ($mode == '-')
                                 $this->user[$m00['numeric']]['modes'] = str_replace($cur, '', $this->user[$m00['numeric']]['modes']);
                        }
                    }
                    if ($cur == 'h') {
                        $tel++;
                        if ($mode == '+') {
                            $this->user[$m00['numeric']]['sethost'] = $args[$tel];
                            if (!is_numeric(strpos($this->user[$m00['numeric']]['modes'], 'h')))
                                $this->user[$m00['numeric']]['modes'] .= $cur;
                        }
                        elseif ($mode == '-') {
                            $this->user[$m00['numeric']]['sethost'] = '';
                            $this->user[$m00['numeric']]['modes'] = str_replace($cur, '', $this->user[$m00['numeric']]['modes']);
                        }
                    }
                }
            }
        }

        private function kickBanneds($channel, $ban) {
            if ($ban == '*!*@*') return;
            $uban = $ban;
            $ban = '`'.$ban.'`';
            $vat = $this->Return_Substrings($ban, '`', '@');
            $vat = $vat[0];
            $aat = str_replace($vat, '', $ban);
            $aat = str_replace('`', '', $aat);

            $vat = $this->banReg($vat);
            $aat = $this->banReg($aat);
            $vat = '^'.$vat;
            foreach ($this->chan[$channel]['users'] as $numeric => $status) {
                if (!$this->isOper($numeric)) {
                    $host = $this->user[$numeric]['host'];
                    $maa = $this->user[$numeric]['nick'].'!'.$this->user[$numeric]['ident'];


                    if (eregi($aat,'@'.$host) && eregi($vat,$maa)) {
                        $victim = true;
                    }

                    $shost = $this->user[$numeric]['sethost'];
                    if ($shost) {
                        if (eregi($aat,'@'.$shost) && eregi($vat,$maa)) {
                            $victim = true;
                        }
                    }

                    if ($this->user[$numeric]['auth']) {
                        $host = $this->user[$numeric]['auth'].'.'.$this->config['vhost'];
                        if (eregi($aat,'@'.$host) && eregi($vat,$maa)) {
                            $victim = true;
                        }
                    }

                    if ($victim == true)
                        $this->SendRaw($this->modek($channel, 'c').' K '.$channel.' '.$numeric.' :Banned.', 1);

                    unset($victim);
                }
            }
        }

        private function kickMatched($channel, $numeric) {
            if (count($this->chan[$channel]['bans']) >= 1) {
                foreach ($this->chan[$channel]['bans'] as $nummer => $ban) {
                    if ($this->ifbanmatch($ban, $numeric)) {
                        $this->SendRaw($this->modek($channel, 'c').' K '.$channel.' '.$numeric.' :Banned.', 1);
                        $nvm = true;
                    }
                }
            }
            if ($nvm) return true;
            else return false;
        }

        private function banReg($mask) {
            $mask = str_replace('[', '\[', $mask);
            $mask = str_replace(']', '\]', $mask);
            $mask = str_replace('.', '\.', $mask);
            $mask = str_replace('?', '.', $mask);
            $mask = str_replace(':', '\:', $mask);
            $mask = str_replace('^', '\^', $mask);
            $mask = str_replace('$', '\$', $mask);
            $mask = str_replace('(', '\(', $mask);
            $mask = str_replace(')', '\)', $mask);
            $mask = str_replace('{', '\{', $mask);
            $mask = str_replace('}', '\}', $mask);
            $mask = str_replace('*', '(.*)', $mask);
            return $mask;
        }

        private function ifbanmatch($ban, $numeric) {
            $uban = $ban;
            $ban = '`'.$ban.'`';
            $vat = $this->Return_Substrings($ban, '`', '@');
            $vat = $vat[0];
            $aat = str_replace($vat, '', $ban);
            $aat = str_replace('`', '', $aat);

            $aat = $this->banReg($aat);
            $vat = $this->banReg($vat);

            $vat = '^'.$vat;
            if (!$this->isOper($numeric)) {
                $host = $this->user[$numeric]['host'];
                $maa = $this->user[$numeric]['nick'].'!'.$this->user[$numeric]['ident'];
                if (eregi($aat,'@'.$host) && eregi($vat,$maa))
                    $victim = true;

                $host = $this->user[$numeric]['sethost'];
                if (eregi($aat,'@'.$host) && eregi($vat,$maa))
                    $victim = true;

                print_r($this->user[$numeric]);
                if ($this->user[$numeric]['auth']) {
                    $host = $this->user[$numeric]['auth'].'.'.$this->config['vhost'];
                    if (eregi($aat,'@'.$host) && eregi($vat,$maa))
                        $victim = true;
                }

                if ($victim == true)
                    return true;
                else return false;
            }
        }

        private function noticeParse($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            if ($args[3] == ':VERSION')
                $this->saveVersions($args);
        }

        private function isGchan($chan) {
            if ($this->user[$this->bots['g']['snumeric']]['channels'][$chan]) return true;
            return false;
        }

        private function isGooglechan($chan) {
            if ($this->user[$this->bots['google']['snumeric']]['channels'][$chan]) return true;
            return false;
        }

        private function invite($args,$Line) {
            if (strtolower($args[2]) == strtolower($this->bots['google']['nickname'])) {
                $chan2 = trim($args[3]);
                $chan2 = strtolower('#'.$chan2);
                for ($i = 1; $i <= strlen($chan2); $i++) {
                    $chan .= $chan2{$i};
                }
                $chan = strtolower($chan);
                if (!$this->isGooglechan($chan)) {
                    $this->joinService($chan, 'google');
                    $argz[] = $this->bots['google']['snumeric'];
                    $argz[] = 'J';
                    $argz[] = $chan;
                    $this->joinChan2($argz);

                    $this->SendRaw($this->bots['google']['snumeric'].' P '.$chan.' :ACTION googles for '.$this->user[$args[0]]['nick'].'.', 1);
                    $this->SendRaw($this->bots['google']['snumeric'].' P '.$chan.' :ACTION found '.rand().' results.', 1);
                    $this->schan[$chan]['google']['moo'] = 'mo';

                }
            }
        }

        private function pmParse($args,$Line) {

            $msg = explode(":",$Line,2);
            $msg = strtolower(trim($msg[1]));

            $parts = explode(" ",$msg);

            $sender = $args[0];

            $snd = trim($parts[1]);
            $dnd = trim($parts[2]);

            if ($this->isGchan(strtolower($args[2]))) {
                $this->helps($args);
            }
            if ($this->isGooglechan(strtolower($args[2]))) {
                $this->google($args);
            }

            // ms commands
            if ($args[2] == $this->bots['ms']['snumeric']) {

                if ($msg == 'showcommands') {
                    $this->showcommands($sender, 'ms');
                }

                elseif ($msg == 'showchannels') {
                    $this->showChannels($sender);
                }

                elseif ($parts[0] == 'channel') {
                    $this->getChannelinfo($sender, $snd);
                }

                elseif ($parts[0] == 'save') {
                    $this->saveData($sender);
                }

                elseif ($parts[0] == 'whois') {
                    $this->getUserinfo($sender, $snd);
                }
                elseif ($parts[0] == 'stats') {
                    $this->countusers($sender);
                }
                elseif ($parts[0] == 'userlist') {
                    $this->userlist($sender);
                }
                elseif ($parts[0] == 'kickall') {
                    $this->kickAll($sender, $args);
                }
                /*elseif ($parts[0] == 'VERSION') {
                    $this->saveVersions($args);
                }*/
                else {
                    if ($parts[0] != 'addchan' && $parts[0] != 'suspend' && $parts[0] != 'unsuspend' && $parts[0] != 'delchan' && $parts[0] != 'banflood' && $parts[0] != 'rejoin' && $parts[0] != 'rejoinall')
                        $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Unknown command.', 1);
                }
            }
            // ms end

            if ($parts[0] == 'addchan') {
                $this->addChan($args);
            }
            elseif ($parts[0] == 'banflood') {
                $this->banflood($args);
            }
            elseif ($parts[0] == 'suspend') {
                $this->suspendChan($args);
            }
            elseif ($parts[0] == 'unsuspend') {
                $this->unsuspendChan($args);
            }
            elseif ($parts[0] == 'delchan') {
                $this->delChan($args);
            }
            elseif ($parts[0] == 'rejoin') {
                $this->rejoin($args);
            }
            elseif ($parts[0] == 'rejoinall') {
                $this->rejoinall($args);
            }

            // chanserv commands
            if ($args[2] == $this->bots['c']['snumeric']) {
                if ($msg == 'showcommands') {
                    $this->showcommands($sender, 'c');
                }
                elseif ($parts[0] == 'chanlev') {
                    $this->changeChanlev($args);
                }
                elseif ($parts[0] == 'hello') {
                    //$this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Command disabled.', 1);
                    //return;
                    $this->hello($args);
                }
                elseif ($parts[0] == 'users') {
                    $this->listauths($args);
                }
                elseif ($parts[0] == 'version') {
                    $this->replyVersion($args, 'c');
                }
                elseif ($parts[0] == 'autolimit') {
                    $this->autolimit($args);
                }
                elseif ($parts[0] == 'welcome') {
                    $this->setWelcome($args);
                }
                elseif ($parts[0] == 'banclear') {
                    $this->banclear($args);
                }
                elseif ($parts[0] == 'deopall') {
                    $this->deopall($args);
                }
                elseif ($parts[0] == 'bandel') {
                    $this->bandel($args);
                }
                elseif ($parts[0] == 'banlist') {
                    $this->banlist($args);
                }
                elseif ($parts[0] == 'addflags') {
                    $this->addLevel($args);
                }
                elseif ($parts[0] == 'remflags') {
                    $this->remLevel($args);
                }
                elseif ($parts[0] == 'showflags') {
                    $this->showLevel($args);
                }
                elseif ($parts[0] == 'whois') {
                    $this->whois($args);
                }
                elseif ($parts[0] == 'chanflags') {
                    $this->changeChanflags($args);
                }
                elseif ($parts[0] == 'newpass') {
                    $sender = trim($args[0]);
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You should not try to change your password this way.', 1);
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Use /msg '.$this->bots['c']['nickname'].'@'.$this->config['cline'].' newpass instead.', 1);
                }
                elseif ($parts[0] == 'op') {
                    $this->op($args);
                }
                elseif ($parts[0] == 'invite') {
                    $this->inviteuser($args);
                }
                elseif ($parts[0] == 'requestpassword') {
                    $this->reqpasswd($args);
                }
                elseif ($parts[0] == 'voice') {
                    $this->voice($args);
                }
                elseif ($parts[0] == 'auth') {
                    $sender = trim($args[0]);
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You should not try to auth this way.', 1);
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Use /msg '.$this->bots['c']['nickname'].'@'.$this->config['cline'].' auth instead.', 1);
                }
                else {
                    if ($parts[0] != 'addchan' && $parts[0] != 'suspend' && $parts[0] != 'unsuspend' && $parts[0] != 'delchan' && $parts[0] != 'banflood' && $parts[0] != 'rejoin' && $parts[0] != 'rejoinall')
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Unknown command.', 1);
                }
            }

            // request commands
            if ($args[2] == $this->bots['r']['snumeric']) {
                if ($msg == 'showcommands')
                    $this->showcommands($sender, 'r');
                elseif ($parts[0] == 'requestbot')
                    $this->requestbot($args);
                elseif ($parts[0] == 'requestspamscan')
                    $this->requestspamscan($args);
            }

            // version commands
            if ($args[2] == $this->bots['s']['snumeric']) {
                if ($msg == 'showcommands')
                    $this->showcommands($sender, 's');
                elseif ($parts[0] == 'showversions')
                    $this->svers($args);
            }
/* dubbel? 13 dec 2007
            // version commands
            if ($args[2] == $this->bots['s']['snumeric']) {
                if ($msg == 'showcommands')
                    $this->showcommands($sender, 's');
                elseif ($parts[0] == 'showversions')
                    $this->svers($args);
            }
*/
            // help commands
            if ($args[2] == $this->bots['g']['snumeric']) {
                if ($msg == 'showcommands')
                    $this->showcommands($sender, 'g');
                elseif ($parts[0] == 'addtopic')
                    $this->topic($args, 'add');
                elseif ($parts[0] == 'remtopic')
                    $this->topic($args, 'del');
                elseif ($parts[0] == 'showtopics')
                    $this->topic($args, 'show');
            }

            //print $this->bots['c']['nickname'].'@'.$this->config['cline'];
            if (strtolower($args[2]) == strtolower($this->bots['c']['nickname'].'@'.$this->config['cline'])) { // someone wants to send a secure message
                if ($parts[0] == 'auth') {
                    $this->authCommand($args);
                }
                elseif ($parts[0] == 'newpass') {
                    $this->newpass($args);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Unknown command.', 1);
            }
            // c end
        }

        private function replyVersion($argz, $service) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            if ($service == 'c')
                $this->SendRaw($this->bots[$service]['snumeric'].' O '.$sender.' :VERSION '.$this->bots[$service]['nickname'].' version 0.1 by Arie & NetIRC (www.NetIRC.eu)', 1);
        }

        private function saveVersions($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $user = $args[0];

            $alip = $this->user[$user]['ip'];
            if ($this->versionallow[$alip]) {
                unset($args[0]);
                unset($args[1]);
                unset($args[2]);
                unset($args[3]);

                foreach ($args as $arg) {
                    $dec .= ' '.$arg;
                }
                $dec = trim($dec);
                for ($i = 0; $i <= strlen($dec); $i++) {
                    $descr .= $dec{$i};
                }
                $descr = str_replace('', '', $descr);
                $ip = $this->IPbase64ToDecimal($this->user[$user]['ip']);

                if ($this->versions) {
                    foreach ($this->versions as $moo => $mee) {
                        if ($moo == $ip) {
                            foreach (array_keys($mee) as $key) {
                                if ($mee[$key] == $descr) $nvm = true;
                            }
                        }
                    }
                }

                if (!$nvm)
                    $this->versions[$ip][] = $descr;
            }
        }

        private function svers($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $chan = strtolower($args[4]);
            if ($this->isOper($sender)) {
                if ($chan) {
                    if (strpos($chan, '#') === 0) {
                        foreach ($this->versions as $moo => $mee) {
                            foreach (array_keys($mee) as $key) {
                                $count[$mee[$key]]++;
                            }
                        }
                        foreach ($count as $version => $number) {
                            if ($number == 1) $klein++;
                            else {
                                $this->SendRaw($this->bots['s']['snumeric'].' P '.$chan.' :'.$version.' ==>> '.$number, 1);
                            }
                            $total += $number;
                        }
                        $this->SendRaw($this->bots['s']['snumeric'].' P '.$chan.' :Versions with 1 hit: '.$klein, 1);
                        $this->SendRaw($this->bots['s']['snumeric'].' P '.$chan.' :Total: '.$total, 1);
                    }
                    else $this->SendRaw($this->bots['s']['snumeric'].' O '.$sender.' :Channels start with a #.', 1);
                }
                else $this->SendRaw($this->bots['s']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['s']['nickname'].' showversions <#channel>', 1);
            }
            else $this->SendRaw($this->bots['s']['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use showversions.', 1);
        }

        private function loadCommands() {

            // C //

            //
            //altijd commands - level -1
            $this->addcommand('c',  '-1', 'SHOWCOMMANDS', '         Lists available commands.');


            //
            //niet authed - level 0
            $this->addcommand('c',  '0', 'HELLO', '                Creates a new user account in the bot.');
            $this->addcommand('c',  '0', 'AUTH ', '                Identifies you as a particular user on the bot.');
            //
            //authed - level 1
            $this->addcommand('c',  '1', 'AUTOLIMIT', '            Shows or changes the autolimit threshold on a channel.');
            $this->addcommand('c',  '1', 'CHANLEV', '              Shows or modifies user access on a channel.');
            $this->addcommand('c',  '1', 'CHANFLAGS', '            Shows or changes the flags on a channel.');
            $this->addcommand('c',  '1', 'OP', '                   Ops you or other users on channel(s)');
            $this->addcommand('c',  '1', 'VOICE', '                Voices you or other users on channel(s).');
            $this->addcommand('c',  '1', 'NEWPASS', '              Changes your password.');
            $this->addcommand('c',  '1', 'REQUESTPASSWORD', '      E-mails an auth\'s password.');
            $this->addcommand('c',  '1', 'BANCLEAR', '             Removes all bans from a channel including persistent bans.');
            $this->addcommand('c',  '1', 'INVITE', '               Invites you to a channel.');
            $this->addcommand('c',  '1', 'BANDEL', '               Removes a single ban from a channel.');
            $this->addcommand('c',  '1', 'BANLIST', '              Displays all persistent bans on a channel.');
            $this->addcommand('c',  '1', 'WHOIS', '                Displays information about a user.');
            $this->addcommand('c',  '1', 'WELCOME', '              Shows or changes the welcome message on a channel.');

            //
            //opercommands - level 2
            //
            $this->addcommand('c',  '2', 'ADDCHAN', '              Adds a channel.');
            $this->addcommand('c',  '2', 'SUSPEND', '              Suspends a channel.');
            $this->addcommand('c',  '2', 'UNSUSPEND', '            Unsuspends a channel.');
            //

            //specialcommands - level 3
            $this->addcommand('c',  '3', 'DELCHAN', '              Deletes a channel.');

            // O //

            //opercommands - level 2
            $this->addcommand('ms', '2', 'CHANNEL', '              Displays information about a channel.');
            $this->addcommand('ms', '2', 'SAVE', '                 Saves all data.');
            $this->addcommand('ms', '2', 'SHOWCHANNELS', '         Lists all channels and the user count.');
            $this->addcommand('ms', '2', 'SHOWCOMMANDS', '         Lists available commands.');
            $this->addcommand('ms', '2', 'USERLIST', '             Lists all users.');
            $this->addcommand('ms', '2', 'WHOIS', '                Displays information about a user.');

            //specialcommands - level 3
            $this->addcommand('ms', '3', 'KICKALL', '              Kicks all users of a channel.');

            // R //

            //authed - level 1
            $this->addcommand('r',  '1', 'REQUESTBOT', '           Requests '.$this->bots['c']['nickname'].' or L to a channel.');
            $this->addcommand('r',  '1', 'REQUESTSPAMSCAN', '      Requests S to a channel.');
            $this->addcommand('r',  '1', 'SHOWCOMMANDS', '         Lists available commands.');

            // G //

            //authed - level 1
            $this->addcommand('g',  '3', 'ADDTOPIC', '             Adds a help topic.');
            $this->addcommand('g',  '3', 'REMTOPIC', '             Removes a help topic.');
            $this->addcommand('g',  '3', 'SHOWTOPICS', '           Lists available topics.');

        }

        private function addCommand($service, $level, $command, $descr) {
            $this->command[$service][$level][$command] = $descr;
        }

        private function isOper($numeric) {
            $modes = $this->user[$numeric]['modes'];
            if (is_numeric(strpos($modes, 'o')) || is_numeric(strpos($modes, 'k')))
                return true;
            else return false;
        }

        private function isOflag($numeric) {
            $modes = $this->user[$numeric]['modes'];
            if (is_numeric(strpos($modes, 'o'))) return true;
            else return false;
        }

        private function isKflag($numeric) {
            $modes = $this->user[$numeric]['modes'];
            if (is_numeric(strpos($modes, 'k'))) return true;
            else return false;
        }

        private function showcommands($sender, $service) {
                $auth = $this->getAuth($sender);

                if ($auth)
                    $level = 1;
                else $level = 0;

                if ($this->isOper($sender) && $auth)
                    $level = 2;

                if ($level == 2 && is_numeric(strpos($this->getAuthlevel($auth), 'd')))
                    $level = 3;

                $this->SendRaw($this->bots[$service]['snumeric'].' O '.$sender.' :The following commands are available to you:', 1);

                if (is_array($this->command[$service])) {
                    foreach ($this->command[$service] as $clevel => $command) {
                        if (($clevel <= $level) || ($clevel == '-1')) {
                            if ($level > 0 && $clevel == 0) continue;
                            foreach (array_keys($command) as $key) {
                                $commands[] = $key.$command[$key];
                            }
                        }
                    }
                    if (count($commands) >= 1) {
                        sort($commands);
                        foreach ($commands as $moo => $mee) {
                            $this->SendRaw($this->bots[$service]['snumeric'].' O '.$sender.' :'.$mee, 1);
                        }
                    }
                }
                else print "Error: No commands shown for $service";
                $this->SendRaw($this->bots[$service]['snumeric'].' O '.$sender.' :End of list.', 1);
        }

        private function addLevel($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $user = strtolower($args[4]);
            $flags = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if ($this->isOper($sender) && is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                    if ($user) {
                        $user2 = $this->nick[$user];
                        if ($user2) {
                            $hisauth = $this->user[$user2]['auth'];
                            if ($hisauth) {
                                if ($flags) {
                                    $this->addAuthLevel($hisauth, $flags);
                                    $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done. Userflags for '.$user.': '.$this->getAuthlevel($hisauth), 1);
                                }
                                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' addflags <user> <flag(s)>', 1);
                            }
                            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :User '.$user.' is not authed.', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Can\'t find '.$user, 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' addflags <user> <flag(s)>', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

    private function remLevel($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $user = strtolower($args[4]);
            $flags = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if ($this->isOper($sender)) {
                    if ($user) {
                        $user2 = $this->nick[$user];
                        if ($user2) {
                            $hisauth = $this->user[$user2]['auth'];
                            if ($flags) {
                                if ($hisauth) {
                                    $this->remAuthLevel($hisauth, $flags);
                                    $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done. Userflags for '.$user.': '.$this->getAuthlevel($hisauth), 1);
                                }
                                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :User '.$user.' is not authed.', 1);
                            }
                            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' remflags <user> <flag(s)>', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Can\'t find '.$user, 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' remflags <user> <flag(s)>', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

        private function showLevel($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $user = strtolower($args[4]);
            $flags = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if ($this->isOper($sender)) {
                    if ($user) {
                        $user2 = $this->nick[$user];
                        if ($user2) {
                            $hisauth = $this->user[$user2]['auth'];
                            if ($hisauth)
                                $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Userflags for '.$user.': '.$this->getAuthlevel($hisauth), 1);
                            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :User '.$user.' is not authed.', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Can\'t find '.$user, 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' showflags <user>', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

        private function suspendChan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $chan = strtolower($args[4]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if ($this->isOper($sender)) {
                    if ($chan) {
                        if (!$this->schan[$chan][$bot]['suspended'] && $this->schan[$chan][$bot]) {
                            if ($args[5]) {
                                unset($args[0]);
                                unset($args[1]);
                                unset($args[2]);
                                unset($args[3]);
                                unset($args[4]);
                                foreach ($args as $arg) {
                                    $dec .= ' '.$arg;
                                }
                                $dec = trim($dec);
                                for ($i = 0; $i <= strlen($dec); $i++) {
                                    $descr .= $dec{$i};
                                }
                                $this->schan[$chan][$bot]['suspended'] = $descr;
                                $this->SendRaw($this->bots[$bot]['snumeric'].' L '.$chan.' :Channel suspended.', 1);
                                $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done.', 1);
                            }
                            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' suspend <#channel> <reason>', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Channel is already suspended.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' suspend <#channel> <reason>', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

        private function authCommand($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $pauth = $this->user[$sender]['auth'];
            if ($pauth) {
                if ($this->auth[strtolower($pauth)]) {
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You are already authed.', 1);
                    return;
                }
            }

            if ($args[5]) {
                if ($this->auth[strtolower($args[4])]) {
                    if ($this->auth[strtolower($args[4])]['password'] == $args[5]) {
                        $this->SendRaw($this->config['numeric'].' AC '.$sender.' '.$this->auth[strtolower($args[4])]['name'], 1);
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Authed succesfully.', 1);

                        $sends[] = $this->bots['c']['snumeric'];
                        $sends[] = 'AC';
                        $sends[] = $sender;
                        $sends[] = $this->auth[strtolower($args[4])]['name'];
                        $this->authUser($sends);
                        return;
                    }
                    else {
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Username or password incorrect.', 1);
                    }
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :No such auth.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].'@'.$this->config['cline'].' auth <username> <password>', 1);
        }

        private function google($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $chan = strtolower($args[2]);

            if ($args[3] == ':!google') {

                if ($args[4]) {
                    $count = count($args);
                    for ($i = 4; $i <= $count; $i++) {
                        $search .= ' '.$args[$i];
                    }
                }
                $search = trim($search);


                $file = file_get_contents('http://www.google.com/search?hl=en&q='.urlencode($search).'&btnG=Zoeken&meta=');

                $blaat = $this->Return_Substrings($file, 'Results <b>', 'for <b>');
                if(isset($blaat[0])) {
                    $aresults = $this->Return_Substrings($blaat[0], 'of about <b>', '</b>');
                    $aresults = $aresults[0];

                    $moo = $this->Return_Substrings($file, '<li class=g><h3 class=r>', '<div class="s">');

                    $count = count($moo);

                    if ($count > 2)
                        $for = 2;
                    else $for = $count;

                    for ($i = 0; $i <= $for; $i++) {

                        if (!is_numeric(strpos($moo[$i], 'http://news.google')) && !is_numeric(strpos($moo[$i], 'http://images.google')) && !is_numeric(strpos($moo[$i], '<cite>www.youtube.com</cite>')) && !empty($moo[$i])) {
                            $title_url = $this->Return_Substrings($moo[$i], '<a href="', '" class=l>');
                            $title_name = $this->Return_Substrings($moo[$i], 'class=l>', '</a></h3>');

                            $title_url = $this->tomirc($title_url[0]);
                            $title_name = $this->tomirc($title_name[0]);

                            $line = "$title_name :: $title_url";

                            $this->SendRaw($this->bots['google']['snumeric'].' P '.$chan.' :'.$line, 1);
                        }
                        else $for++;
                    }
                }
                else {
                    $this->SendRaw($this->bots['google']['snumeric'].' P '.$chan.' :No results', 1);
                }

            }
        }

        private function hello($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];

            if (!$this->authflood[$sender]) {
                $nick = $this->user[$sender]['nick'];

                $pauth = $this->user[$sender]['auth'];
                if ($pauth) {
                    if ($this->auth[strtolower($pauth)]) {
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You are already authed.', 1);
                        return;
                    }
                }


                if ($this->auth[strtolower($nick)]) {
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Someone\'s already registered with that account name.', 1);
                    return;
                }

                if ($this->authCheck($nick)) {
                    if ($args[5]) {
                        if ($args[4] == $args[5]) {
                            if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $args[4])) {
                                // Everything is ok.

                                $email = $args[4];
                                $password = $this->genPass();
                                $this->db->query("
                                    INSERT INTO auths (name, password, createdate, email)
                                    VALUES ('$nick', $password, ".time().", '$email')
                                ");
                                $this->db->query("
                                    SELECT *
                                    FROM auths
                                    WHERE name = '$nick'
                                ");
                                if ($this->db->num_rows()) {
                                    $row = $this->db->fetch_assoc();
                                    $this->auth[strtolower($row['name'])] = array(
                                        'ctime' => $row['createdate'],
                                        'name' => $row['name'],
                                        'userflags' => $row['userflags'],
                                        'email' => $row['email'],
                                        'password' => $row['password'],
                                        'id' => $row['id']
                                    );

                                    $this->email[$email][] = $row['name'];
                                }
                                else {
                                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :An error occurred while processing your auth. Please tell #help.', 1);
                                    return;
                                }

                                $this->authflood[$sender] = true;

                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Your account has been created. An e-mail with this info has been send to '.$email.' with your password. You can now login with your account. (/msg '.$this->bots['c']['nickname'].' auth)', 1);

                                $bericht =
"Hello $nick,

You or someone else has created an account on the NetIRC service bot ".$this->bots['c']['nickname'].". If you did not do this, you can ignore this e-mail.

Login info:
U: $nick
P: $password

Thank you for using NetIRC.

---
This e-mail has been generated by ".$this->bots['c']['nickname']." (".$this->config['site'].")";

                                mail($email, 'Your '.$this->bots['c']['nickname'].' account', $bericht, 'From: "NetIRC" <Q@Service.NetIRC.eu>');

                                return;
                            }
                            else {
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :This is not an email address.', 1);
                                return;
                            }
                        }
                        else {
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Email addresses do not match.', 1);
                            return;
                        }
                    }
                    else {
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' hello user@mymailhost.xx user@mymailhost.xx', 1);
                        return;
                    }
                }
                else {
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Invalid characters found in your auth. Please change your nick without any special chars.', 1);
                    return;
                }
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You can only create once an account.', 1);
        }

        private function genPass() {
            return rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);
        }

        private function authCheck($auth) {
            $allowed = array('A' => true, 'B' => true, 'C' => true, 'D' => true, 'E' => true, 'F' => true, 'G' => true, 'H' => true, 'I' => true, 'J' => true, 'K' => true, 'L' => true, 'M' => true, 'N' => true,
        'O' => true, 'P' => true, 'Q' => true, 'R' => true, 'S' => true, 'T' => true, 'U' => true, 'V' => true, 'W' => true, 'X' => true, 'Y' => true, 'Z' => true, 'a' => true, 'b' => true,
        'c' => true, 'd' => true, 'e' => true, 'f' => true, 'g' => true, 'h' => true, 'i' => true, 'j' => true, 'k' => true, 'l' => true, 'm' => true, 'n' => true, 'o' => true, 'p' => true,
        'q' => true, 'r' => true, 's' => true, 't' => true, 'u' => true, 'v' => true, 'w' => true, 'x' => true, 'y' => true, 'z' => true, '0' => true, '1' => true, '2' => true, '3' => true,
        '4' => true, '5' => true, '6' => true, '7' => true, '8' => true, '9' => true, '-' => true);
        $auth = trim($auth);
        for ($i = 0; $i < strlen($auth); $i++) {
            if (!$allowed[$auth{$i}]) {
                return false;
            }
        }
        return true;
        }

        private function banflood($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $chan = strtolower($args[4]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if (is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                    if ($chan) {
                        for ($i = 0; $i <= 500; $i++) {
                            $ban = rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).'!'.rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).'@'.rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9).rand(1, 9);
                            $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +b '.$ban, 1);
                        }
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' banflood <#channel>', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

        private function unsuspendChan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $chan = strtolower($args[4]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth && $this->isOper($sender)) {
                if ($chan) {
                    if ($this->schan[$chan][$bot]['suspended']) {
                        unset($this->schan[$chan][$bot]['suspended']);
                        if (($bot == 'c' && is_numeric(strpos($this->schan[$chan][$bot]['flags'], 'j'))) || ($bot != 'c')) {
                            $this->joinService($chan, $bot);
                        }
                        $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Channel is not suspended.', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' unsuspend <#channel>', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

        private function numtoBot($num) {
            foreach ($this->bots as $bot => $mee) {
                if ($this->bots[$bot]['snumeric'] == $num) return $bot;
            }
            return false;
        }

        private function botToName($nick) {
            foreach ($this->bots as $bot => $mee) {
                if (strtolower($this->bots[$bot]['nickname']) == strtolower($nick)) return $this->bots[$bot]['snumeric'];
            }
            return false;
        }

        private function topic($argz, $option) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $auth = $this->getAuth($sender);
            $bot = 'g';
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }

            if ($this->isOper($sender) && is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                if ($option == 'add') {
                    $topic = strtolower($args[4]);
                    $count = count($args);
                    for ($i = 5; $i <= $count; $i++) {
                        $answer .= ' '.$args[$i];
                    }

                    if ($topic && $answer) {
                        if (!$this->helps[$topic]) {
                            $this->helps[$topic] = trim($answer);
                            $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done.', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :That topic already exists.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Insufficient parameters.', 1);
                }
                elseif ($option == 'del') {
                    $topic = strtolower($args[4]);
                    if ($this->helps[$topic]) {
                        unset($this->helps[$topic]);
                        $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Topic deleted.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :That topic does not exists.', 1);
                }
                elseif ($option == 'show') {
                    if (count($this->helps) >= 1) {
                        foreach ($this->helps as $topic => $answer) {
                            if ($topic && $answer)
                                $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :'.$topic.' [::] '.$answer, 1);
                        }
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :There are currently no topics.', 1);
                }
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

        private function helps($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[2]);
            $bot = 'g';
            $status = $this->chan[$chan]['users'][$sender];
            if ($args[3] == ':??' && $args[4]) {
                $tag = strtolower($args[4]);
                if ($this->helps[$tag]) {
                    $user = $args[5];
                    $status = $this->chan[$chan]['users'][$sender];
                    if ($status == 'opped' || $status == 'opvoiced' || ($status == 'voiced' && ($chan == $this->config['help'] || $chan == $this->config['helps']))) {
                        if ($user) $add .= $user.': ';
                    }
                    $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$chan.' :'.$add.$this->helps[$tag], 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' P '.$chan.' :No help on this topic found.', 1);
            }
            elseif ($args[3] == ':-out' && $args[4]) {
                if ($status == 'opped' || $status == 'opvoiced') {
                    $user = $this->nick[strtolower($args[4])];
                    if ($this->chan[$chan]['users'][$user]) {
                        $reason = 'Banned.';
                        if ($args[5]) {
                            $count = count($args);
                            unset($reason);
                            for ($i = 5; $i <= $count; $i++) {
                                $reason .= ' '.$args{$i};
                            }
                        }

                        $ban = '*!'.$this->user[$user]['ident'].'@'.$this->user[$user]['host'];
                        $this->SendRaw($this->bots[$bot]['snumeric'].' M '.$chan.' +b '.$ban, 1);
                        $this->SendRaw($this->bots[$bot]['snumeric'].' K '.$chan.' '.$user.' :'.trim($reason), 1);
                        $this->removeUserChannels($user, $chan);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :That user is not on '.$chan.'.', 1);
                }
            }
            elseif ($args[3] == ':-allout') {
                if (($status == 'opped' || $status == 'opvoiced') && ($this->isOper($sender))) {
                    $user = $this->nick[strtolower($args[4])];
                    if (is_array($this->chan[$chan]['users'])) {
                        foreach ($this->chan[$chan]['users'] as $user => $status) {
                            if ($status == 'regged' && !$this->isOper($user)) {
                                if ($this->user[$user]['ident']) {
                                    $ban = '*!'.$this->user[$user]['ident'].'@'.$this->user[$user]['host'];
                                    $this->chan[$chan]['bans'] [] = $ban;

                                    $this->removeUserChannels($user, $chan);
                                    $kickusers[] = $ban;
                                    $kicku[] = $user;
                                }
                            }
                        }
                        if (is_array($kickusers)) {
                            $count = count($kickusers);
                            for ($i = 0,$y = 0; $i <= $count; $i++) {
                                $som = $i / 6;
                                if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                                    for ($x = 0; $x <= 5; $x++)
                                        unset($kickusers[$x]);
                                    $y++;
                                }
                                $nicks2kick[$y] .= ' '.$kickusers[$i];
                            }
                            foreach ($nicks2kick as $oprow) {
                                $moo = trim($oprow);
                                $moo = explode(' ', $moo);
                                $count = count($moo);
                                for ($i = 0; $i < $count; $i++) {
                                    $oc .= 'b';
                                }
                                $this->SendRaw($this->modek($chan, $bot).' M '.$chan.' +'.$oc.' '.trim($oprow), 1);
                                unset($oc);
                            }
                        }
                        $reason = 'Only sit on '.$chan.' when you need help.';
                        if (is_array($kicku)) {
                            $reason = trim($reason);
                            foreach ($kicku as $ukick) {
                                $this->SendRaw($this->bots[$bot]['snumeric'].' K '.$chan.' '.$ukick.' :'.$reason, 1);
                            }
                        }
                    }
                }
            }
        }

        private function addChan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $bot = $this->numtoBot($args[2]);
            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if ($this->isOper($sender)) {
                    if ($chan) {
                        if (!$this->schan[$chan][$bot]) {
                            if (!$user) $user = strtolower($this->user[$sender]['nick']);
                            if (strpos($user, '#') === 0) {
                                for ($i = 1; $i <= strlen($user); $i++) {
                                    $okauth .= $user{$i};
                                }
                                if (!$this->auth[strtolower($okauth)]) {
                                    $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Unknown user '.$user.' ('.$okauth.').', 1);
                                    return;
                                }
                            }
                            else {
                                $unum = $this->nick[strtolower($user)];
                                if ($this->user[$unum]) {
                                    $uauth = $this->getAuth($unum);
                                }
                            }
                            if ($this->auth[strtolower($uauth)] || $okauth) {
                                if ($okauth) $uauth = $okauth;
                                $this->joinService($chan, $bot);
                                $this->chanlev[$chan][$uauth] = 'amnov';
                                $this->rchanlev[$uauth][$chan] = 'amnov';
                                $this->schan[$chan][$bot]['flags'] = 'j';
                                $this->schan[$chan][$bot]['moo'] = 'mo';
                                $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done.', 1);
                            }
                            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :User '.$user.' is not authed.', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Channel is already in the database.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' addchan <#channel> [<nickname|#authname>]', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

        private function delChan($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if ($this->isOper($sender)) {
                    if ($chan) {
                        if ($this->schan[$chan][$bot]) {
                            unset($this->schan[$chan][$bot]);
                            if ($bot == 'c') {
                                foreach ($this->chanlev[$chan] as $uauth => $status) {
                                    unset($this->rchanlev[$uauth][$chan]);
                                }
                                unset($this->lchan[$chan]);
                                unset($this->chanlev[$chan]);
                            }
                            unset($this->welcome[$chan][$bot]);
                            $this->SendRaw($this->bots[$bot]['snumeric'].' L '.$chan.' :Channel deleted.', 1);
                            $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done.', 1);
                            $this->leaveService($bot, $chan);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :No such channel.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' delchan <#channel>', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }
            private function rejoin($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);

            if (!$bot) {
                print "\n\n!! Fout bij rejoin\n\n";
                print_r($args);
                print "\n\n-- end --\n\n";
            }

            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if (is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                    if ($chan) {
                        if ($this->schan[$chan][$bot]) {
                            if ($bot == 'c' && !is_numeric(strpos($this->schan[$chan][$bot]['flags'], 'j'))) {
                                $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Chanflag -j on that channel.', 1);
                                return;
                            }
                            $this->SendRaw($this->bots[$bot]['snumeric'].' L '.$chan.' :Cycling channel.', 1);
                            $this->joinService($chan, $bot);
                            $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done.', 1);
                        }
                        else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :No such channel.', 1);
                    }
                    else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots[$bot]['nickname'].' rejoin <#channel>', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }

            private function rejoinall($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $sender = $args[0];
            $bot = $this->numtoBot($args[2]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($sender);
            if ($auth) {
                if (is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                    foreach ($this->schan as $chan => $info) {
                        if ($this->schan[$chan][$bot] && !$this->schan[$chan][$bot]['suspended']) {
                            if (($bot == 'c' && is_numeric(strpos($this->schan[$chan][$bot]['flags'], 'j'))) || ($bot != 'c')) {
                                $this->SendRaw($this->bots[$bot]['snumeric'].' L '.$chan.' :Cycling channel.', 1);
                                $this->joinService($chan, $bot);
                            }
                        }
                    }
                    $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :Done.', 1);
                }
                else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
            }
            else $this->SendRaw($this->bots[$bot]['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use '.$command.'.', 1);
        }


        private function changeChanflags($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);

            $changeflags = strtolower($args[5]);

            $auth = $this->getAuth($args[0]);

            if ($auth) {
                if ($chan) {
                    if (!$this->schan[$chan]['c']['suspended'] && $this->schan[$chan]['c']) {
                        $flags = $this->chanlev[$chan][$auth];
                        if ($changeflags) {
                            if (is_numeric(strpos($flags, 'm')) || is_numeric(strpos($flags, 'n')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                                for ($i = 0; $i <= strlen($changeflags); $i++) {
                                    $cur = $changeflags{$i};
                                    //print "cur: $cur\n";

                                    if ($cur == '-') $mode = $cur;
                                    if ($cur == '+') $mode = $cur;

                                    $moden = array('a','b','c','e','f','g','i','j','k','p','t','v','w');

                                    foreach ($moden as $m00 => $moo) {
                                        if ($mode == '-') {
                                            if ($moo == $cur) {
                                                if ($cur == 'j' && !$floodmode) {
                                                    $this->SendRaw($this->bots['c']['snumeric'].' L '.$chan, 1);
                                                    $this->leaveService('c', $chan);
                                                    $this->schan[$chan]['c']['flags'] = str_replace($cur, '', $this->schan[$chan]['c']['flags']);
                                                    $floodmode = 'moo';
                                                }
                                                if ($cur == 'c' && !$floodcmode) {
                                                    $this->schan[$chan]['c']['flags'] = str_replace($cur, '', $this->schan[$chan]['c']['flags']);
                                                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' -l ', 1);
                                	                $this->chan[$chan]['limit'] = '';
                                                    $floodcmode = 'moo';
                                                }
                                                else {
                                                    if ($cur != 'c' && $cur != 'j')
                                                        $this->schan[$chan]['c']['flags'] = str_replace($cur, '', $this->schan[$chan]['c']['flags']);
                                                }
                                            }
                                        }

                                        if ($mode == '+') {
                                            if ($moo == $cur) {
                                                if (!is_numeric(strpos($this->schan[$chan]['c']['flags'], $cur))) {
                                                    if ($cur == 'j' && !$floodmode) {
                                                        $this->joinService($chan, 'c');
                                                        $floodmode = 'moo';
                                                        $this->schan[$chan]['c']['flags'] .= $cur;
                                                    }
                                                    if ($cur == 'c' && !$floodcmode) {
                                                        $this->setLimit($chan);
                                                        $floodcmode = 'moo';
                                                        $this->schan[$chan]['c']['flags'] .= $cur;
                                                    }
                                                    if ($cur == 'b' && !$floodbmode) {
                                                        $this->deopNonA($chan);
                                                        $floodbmode = 'moo';
                                                        $this->schan[$chan]['c']['flags'] .= $cur;
                                                    }
                                                    if ($cur == 'p' && !$floodomode) {
                                                        $this->opAllC($chan);
                                                        $floodomode = 'moo';
                                                        $this->schan[$chan]['c']['flags'] .= $cur;
                                                    }
                                                    if ($cur == 'k' && !$floodkmode) {
                                                        $this->kickAllNonC($chan);
                                                        $floodkmode = 'moo';
                                                        $this->schan[$chan]['c']['flags'] .= $cur;
                                                    }
                                                    else {
                                                        if ($cur != 'c' && $cur != 'j' && $cur != 'p' && $cur != 'b')
                                                            $this->schan[$chan]['c']['flags'] .= $cur;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done.', 1);
                                $this->schan[$chan]['c']['flags'] = $this->convert($this->schan[$chan]['c']['flags']);
                                if ($this->schan[$chan]['c']['flags'])
                                    $flags = '+'.$this->schan[$chan]['c']['flags'];
                                else $flags = 'none';
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Current channel flags for '.$chan.': '.$flags, 1);
                            }
                            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to change the chanflags.', 1);
                        }
                        else {
                            if (is_numeric(strpos($flags, 'm')) || is_numeric(strpos($flags, 'n')) || is_numeric(strpos($flags, 'o')) || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                                if ($this->schan[$chan]['c']['flags'])
                                    $flags = '+'.$this->schan[$chan]['c']['flags'];
                                else $flags = 'none';
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Current channel flags for '.$chan.': '.$flags, 1);
                            }
                        }
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' chanflags <#channel> [+|-] [<flags>]', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :chanflags is only available to authed users.', 1);
        }

        private function kickAllNonC($chan) {
            if (is_array($this->chan[$chan])) {
                foreach ($this->chan[$chan]['users'] as $user => $status) {
                    if (!$this->isOper($user)) {
                        $auth = $this->user[$user]['auth'];
                        if ($auth)
                            $chanlev = $this->chanlev[$chan][strtolower($auth)];
                        else $chanlev = '';
                        if (!$chanlev) {
                            $ban = '*!'.$this->user[$user]['ident'].'@'.$this->user[$user]['host'];

                            $this->chan[$chan]['bans'] [] = $ban;

                            $this->removeUserChannels($user, $chan);
                            $kickusers[] = $ban;
                            $kicku[] = $user;
                        }
                    }
                }
                if (is_array($kickusers)) {
                    $count = count($kickusers);
                    for ($i = 0,$y = 0; $i <= $count; $i++) {
                        $som = $i / 6;
                        if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                            for ($x = 0; $x <= 5; $x++)
                                unset($kickusers[$x]);
                            $y++;
                        }
                        $nicks2kick[$y] .= ' '.$kickusers[$i];
                    }
                    foreach ($nicks2kick as $oprow) {
                        $moo = trim($oprow);
                        if ($moo) {
                            $moo = explode(' ', $moo);
                            $count = count($moo);
                            for ($i = 0; $i < $count; $i++) {
                                $oc .= 'b';
                            }
                            $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +'.$oc.' '.trim($oprow), 1);
                        }
                        unset($oc);
                    }
                }
                $reason = 'Authorised users only.';
                if (is_array($kicku)) {
                    foreach ($kicku as $ukick) {
                        $this->SendRaw($this->modek($chan, 'c').' K '.$chan.' '.$ukick.' :'.$reason, 1);
                    }
                }
            }
        }

        private function deopNonA($chan) {
            foreach ($this->chan[$chan]['users'] as $user => $status) {
                if ($status == 'opped' || $status == 'opvoiced') {
                    if (!$this->isOper($user)) {
                        $auth = $this->user[$user]['auth'];
                        if ($auth)
                        $chanlev = $this->chanlev[$chan][strtolower($auth)];

                        if (!is_numeric(strpos($chanlev, 'o')) && !is_numeric(strpos($chanlev, 'm')) && !is_numeric(strpos($chanlev, 'n'))) {
                            $deopusers[] = $user;
                            if ($status == 'opped')
                                $this->setStatus($user, $chan, 'regged');
                            elseif ($status == 'opvoiced')
                                $this->setStatus($user, $chan, 'voiced');
                        }
                        unset($chanlev);
                    }
                }
            }
            if (is_array($deopusers)) {
                $count = count($deopusers);
                for ($i = 0,$y = 0; $i <= $count; $i++) {
                    $som = $i / 6;
                    if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                        for ($x = 0; $x <= 5; $x++)
                            unset($deopusers[$x]);
                        $y++;
                    }
                    $nicks2op[$y] .= ' '.$deopusers[$i];
                }
                foreach ($nicks2op as $oprow) {
                    $moo = trim($oprow);
                    $moo = explode(' ', $oprow);
                    $count = count($moo);
                    for ($i = 1; $i < $count; $i++) {
                        $oc .= 'o';
                    }
                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' -'.$oc.' '.trim($oprow), 1);
                    unset($oc);
                }
            }
        }

        private function opAllC($chan) {
            foreach ($this->chan[$chan]['users'] as $user => $status) {
                if ($status == 'voiced' || $status == 'regged') {
                    $auth = $this->user[$user]['auth'];
                    if ($auth)
                    $chanlev = $this->chanlev[$chan][strtolower($auth)];

                    if ($chanlev) {
                        if (is_numeric(strpos($chanlev, 'o')) || is_numeric(strpos($chanlev, 'm')) || is_numeric(strpos($chanlev, 'n'))) {
                            $deopusers[] = $user;
                            if ($status == 'voiced')
                                $this->setStatus($user, $chan, 'opvoiced');
                            elseif ($status == 'regged')
                                $this->setStatus($user, $chan, 'opped');
                        }
                        unset($chanlev);
                    }
                }
            }
            if (is_array($deopusers)) {
                $count = count($deopusers);
                for ($i = 0,$y = 0; $i <= $count; $i++) {
                    $som = $i / 6;
                    if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                        for ($x = 0; $x <= 5; $x++)
                            unset($deopusers[$x]);
                        $y++;
                    }
                    $nicks2op[$y] .= ' '.$deopusers[$i];
                }
                foreach ($nicks2op as $oprow) {
                    $moo = trim($oprow);
                    $moo = explode(' ', $oprow);
                    $count = count($moo);
                    for ($i = 1; $i < $count; $i++) {
                        $oc .= 'o';
                    }
                    $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +'.$oc.' '.trim($oprow), 1);
                    unset($oc);
                }
            }
        }

        private function setLimit($chan) {
            $limit = count($this->chan[$chan]['users']);
            if ($this->schan[$chan]['c']['autolimit'])
               $limit += $this->schan[$chan]['c']['autolimit'];
            else $limit += 10;
            if ($limit != $this->chan[$chan]['limit']) {
                $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +l '.$limit.' ', 1);
                $this->chan[$chan]['limit'] = $limit;
            }
        }

        private function changeChanlev($argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            $sender = $args[0];
            $chan = strtolower($args[4]);
            $user = strtolower($args[5]);
            for ($i = 1; $i <= strlen($args[3]); $i++) {
                $command .= $args[3]{$i};
            }
            $auth = $this->getAuth($args[0]);

            if ($auth) {

            $changeflags = strtolower($args[6]);
            if ($chan) {

                if ($this->schan[$chan]['c'] && !$this->schan[$chan]['c']['suspended']) {
                    $flags = $this->chanlev[$chan][$auth];

                    if ($flags || is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd'))) {
                        if ($user) {
    //Just a few tabs back; there were to many tabs :P
    if (strpos($user, '#') === 0) {
        for ($i = 1; $i <= strlen($user); $i++) {
            $okauth .= $user{$i};
        }
        if (!$this->auth[strtolower($okauth)]) {
            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Unknown user '.$user.'.', 1);
            return;
        }
    }
    else {
        $unum = $this->nick[strtolower($user)];
        if ($this->user[$unum]) {
            $uauth = $this->getAuth($unum);
        }
    }
    if ($this->auth[strtolower($uauth)] || $okauth) {
        if ($okauth) $uauth = $okauth;
        $uflags = $this->chanlev[$chan][$uauth];
        if ($changeflags) { // sender wants to change something
            if ((is_numeric(strpos($flags, 'n'))) || (is_numeric(strpos($flags, 'm'))
                && !is_numeric(strpos($changeflags, 'm'))
                && !is_numeric(strpos($changeflags, 'n')))
                || ($uauth == $this->user[$sender]['auth']
                && !is_numeric(strpos($changeflags, '+')))
                || (is_numeric(strpos($this->getAuthlevel($this->user[$sender]['auth']), 'd')))) {
                    $oldflags = $this->chanlev[$chan][$uauth];
                    for ($i = 0; $i <= strlen($changeflags); $i++) {
                        $cur = $changeflags{$i};
                        //print "cur: $cur\n";

                        if ($cur == '-') $mode = $cur;
                        if ($cur == '+') $mode = $cur;

                        $moden = array('a','m','n','o','v','t','k','b','i');

                        foreach ($moden as $m00 => $moo) {
                            if ($mode == '-') {
                                if ($moo == $cur) {
                                    $this->chanlev[$chan][$uauth] = str_replace($cur, '', $this->chanlev[$chan][$uauth]);
                                    $this->rchanlev[$uauth][$chan] = str_replace($cur, '', $this->rchanlev[$uauth][$chan]);
                                    $this->auth[$uauth]['channels'][$chan] = str_replace($cur, '', $this->rchanlev[$uauth][$chan]);
                                }
                            }
                            if ($mode == '+') {
                                if ($moo == $cur) {
                                    if (!is_numeric(strpos($this->chanlev[$chan][$uauth], $cur))) {
                                        $this->chanlev[$chan][$uauth] .= $cur;
                                        $this->rchanlev[$uauth][$chan] .= $cur;
                                        $this->auth[$uauth]['channels'][$chan] .= $cur;
                                    }
                                }
                            }
                        }
                    }
                    $this->chanlev[$chan][$uauth] = $this->convert($this->chanlev[$chan][$uauth]);
                    $this->rchanlev[$uauth][$chan] = $this->convert($this->rchanlev[$uauth][$chan]);

                    if (is_numeric(strpos($this->chanlev[$chan][$uauth], 'o')) && !is_numeric(strpos($oldflags, 'o')) && is_numeric(strpos($this->schan[$chan]['c']['flags'], 'p'))) { // opping user(s) if the auth has been giving the o chanlev & chanflag p
                        if (is_array($this->auth[$uauth]['users'])) {
                            foreach ($this->auth[$uauth]['users'] as $uzer) {
                                $ustatus = $this->user[$uzer]['channels'][$chan];
                                if (($ustatus) && ($ustatus != 'opped' || $ustatus != 'opvoiced')) {
                                    $opnicks[] = $uzer;
                                    if ($ustatus == 'regged')
                                        $this->setStatus($uzer, $chan, 'opped');
                                    elseif ($ustatus == 'voiced')
                                        $this->setStatus($uzer, $chan, 'opvoiced');
                                }
                            }
                            $count = count($opnicks);
                            for ($i = 0,$y = 0; $i <= $count; $i++) {
                                $som = $i / 6;
                                if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                                    for ($x = 0; $x <= 5; $x++)
                                        unset($opnicks[$x]);
                                    $y++;
                                }
                                $nicks2op[$y] .= ' '.$opnicks[$i];
                            }
                            foreach ($nicks2op as $oprow) {
                                $moo = trim($oprow);
                                $moo = explode(' ', $oprow);
                                $count = count($moo);
                                for ($i = 1; $i < $count; $i++) {
                                    $oc .= 'o';
                                }
                                $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' +'.$oc.' '.trim($oprow), 1);
                                unset($oc);
                            }
                        }
                    }
                    elseif (!is_numeric(strpos($this->chanlev[$chan][$uauth], 'o')) && is_numeric(strpos($oldflags, 'o')) && is_numeric(strpos($this->schan[$chan]['c']['flags'], 'b'))) { // deopping user(s) if the o chanlev has been removed from the auth& chanflag b
                        if (is_array($this->auth[$uauth]['users'])) {
                            foreach ($this->auth[$uauth]['users'] as $uzer) {
                                $ustatus = $this->user[$uzer]['channels'][$chan];
                                if (($ustatus) && ($ustatus == 'opped' || $ustatus == 'opvoiced')) {
                                    $opnicks[] = $uzer;
                                    if ($ustatus == 'opped')
                                        $this->setStatus($uzer, $chan, 'regged');
                                    elseif ($ustatus == 'opvoiced')
                                        $this->setStatus($uzer, $chan, 'voiced');
                                }
                            }
                            $count = count($opnicks);
                            for ($i = 0,$y = 0; $i <= $count; $i++) {
                                $som = $i / 6;
                                if ($i !== 0 && !is_numeric(strpos($som, '.'))) {
                                    for ($x = 0; $x <= 5; $x++)
                                        unset($opnicks[$x]);
                                    $y++;
                                }
                                $nicks2op[$y] .= ' '.$opnicks[$i];
                            }
                            foreach ($nicks2op as $oprow) {
                                $moo = trim($oprow);
                                $moo = explode(' ', $oprow);
                                $count = count($moo);
                                for ($i = 1; $i < $count; $i++) {
                                    $oc .= 'o';
                                }
                                $this->SendRaw($this->modek($chan, 'c').' M '.$chan.' -'.$oc.' '.trim($oprow), 1);
                                unset($oc);
                            }
                        }
                    }
                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Done.', 1);
                    if ($this->chanlev[$chan][$uauth])
                        $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Flags for '.$user.' on '.$chan.': +'.$this->chanlev[$chan][$uauth], 1);
                    else {
                        unset($this->chanlev[$chan][$uauth]);
                        unset($this->rchanlev[$uauth][$chan]);
                        if (!$this->chanlev[$chan]) {
                            unset($this->schan[$chan]['c']);
                            $this->SendRaw($this->bots['c']['snumeric'].' L '.$chan.' :No registered users.', 1);
                        }
                    }
                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Invalid or disallowed flag specified.', 1);
        }
        else { // sender wants someone's chanlev
            if ($uflags) {
                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Flags for '.$user.' on '.$chan.': +'.$uflags, 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :User '.$user.' is not known on '.$chan.'.', 1);
        }
    }
    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :User '.$user.' is not authed.', 1);
    // end of back tabbing
                        }
                        else {
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Known users on '.$chan.':', 1);
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Username        Flags', 1);
                            if (is_array($this->chanlev[$chan])) {
                                $spaces = '                 ';
                                foreach ($this->chanlev[$chan] as $name => $modes) {
                                    $cur = $this->auth[$name]['name'];
                                    $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.substr_replace($spaces, $cur, 1, strlen($cur)).'+'.$modes, 1);
                                }
                            }
                            else {
                                $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :An error occurred while processing chanlev for channel '.$chan.'. Please tell #help.', 1);
                                return;
                            }
                            $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :End of list.', 1);
                        }
                    }
                    else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :You do not have sufficient access on '.$chan.' to use chanlev.', 1);

                }
                else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Channel '.$chan.' is unknown or suspended.', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :Usage: /msg '.$this->bots['c']['nickname'].' chanlev <#channel> [<nickname|#authname>] [<flags>]', 1);
            }
            else $this->SendRaw($this->bots['c']['snumeric'].' O '.$sender.' :'.$command.' is only available to authed users.', 1);
        }

        private function convert($args) {
            for ($i = 0; $i <= strlen($args); $i++) {
                $total[] = $args{$i};
            }
            sort($total);
            foreach ($total as $moo) {
                $ret .= $moo;
            }
            return $ret;
        }

        private function getAuth($numeric) {
            $auth = $this->user[$numeric]['auth'];
            if ($auth) return strtolower($auth);
            else return 0;
        }

        private function getAuthlevel($auth) {
            return $this->auth[strtolower($auth)]['userflags'];
        }

        private function addAuthLevel($auth, $flag) {
            $flags = $this->auth[strtolower($auth)]['userflags'];
            if (!is_numeric(strpos($this->auth[strtolower($auth)]['userflags'], $flag))) {
                $flags .= $flag;
                $this->convert($flags);
                $this->auth[strtolower($auth)]['userflags'] = $flags;
            }
        }
        private function remAuthLevel($auth, $flag) {
            $flags = str_replace($flag, '', $this->auth[strtolower($auth)]['userflags']);
            $this->auth[strtolower($auth)]['userflags'] = $flags;
        }

        private function kickAll($sender, $argz) {
            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }
            $chan = strtolower($args[4]);
            $count = count($args);

            unset($args[0]);
            unset($args[1]);
            unset($args[2]);
            unset($args[3]);
            unset($args[4]);
            for ($i = 0; $i <= $count; $i++) {
                $reason .= ' '.$args{$i};
            }

            $auth = $this->getAuth($sender);
            if ($auth) {
                if (is_numeric(strpos($this->getAuthlevel($auth), 'd'))) {
                    if (strpos($chan, '#') === 0) {
                        foreach ($this->chan[$chan]['users'] as $user => $status) {
                            if (!$this->isOper($user) && $status) {
                                $this->SendRaw($this->config['numeric'].' K '.$chan.' '.$user.' :'.trim($reason), 1);
                                $this->removeUserChannels($user, $chan);
                            }
                        }
                    }
                    else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Channels start with a #.', 1);
                }
                else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :You don\'t have access to this command.', 1);
            }
            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :You don\'t have access to this command.', 1);
        }

        private function getAuths() {
            $this->db->query("
                SELECT *
                FROM auths
            ");
            if ($this->db->num_rows()) {
                while ($row = $this->db->fetch_assoc()) {
                    $this->auth[strtolower($row['name'])] = array(
                        'ctime' => $row['createdate'],
                        'name' => $row['name'],
                        'userflags' => $row['userflags'],
                        'email' => $row['email'],
                        'password' => $row['password'],
                        'id' => $row['id'],
                        'channels' => $this->setChanlevs($row['id'])
                    );
                    $this->email[strtolower($row['email'])][] = $row['name'];
                }
            }
        }

        private function setChanlevs($id) {
            $sql = mysql_query("
                SELECT *
                FROM chanlevs
                WHERE auth = $id
            ");
            if (mysql_num_rows($sql)) {
                while ($row = mysql_fetch_assoc($sql)) {
                    $chanlevs[$row['channel']] = $row['flags'];
                }
                return $chanlevs;
            }
            return false;
        }

        private function gethelps() {
            $this->db->query("
                SELECT *
                FROM helps
            ");
            if ($this->db->num_rows()) {
                while ($row = $this->db->fetch_assoc()) {
                    $this->helps[strtolower($row['tag'])] = trim($row['answer']);
                }
            }
        }

        private function getWelcomes() {
            $this->db->query("
                SELECT *
                FROM welcomes
            ");
            if ($this->db->num_rows()) {
                while ($row = $this->db->fetch_assoc()) {
                    $this->welcome[$row['channel']][$row['service']] = $row['message'];
                }
            }
        }

        private function getChanlevs() {
            $this->db->query("
                SELECT *
                FROM chanlevs
            ");
            if ($this->db->num_rows()) {
                while ($row = $this->db->fetch_assoc()) {
                    foreach ($this->auth as $moo => $mee) {
                        if ($row['auth'] == $mee['id']) {
                            $this->chanlev[strtolower($row['channel'])][strtolower($moo)] = $row['flags'];
                            $this->rchanlev[strtolower($moo)][strtolower($row['channel'])] = $row['flags'];
                        }
                    }
                }
            }
        }

        private function getUserinfo($sender, $nick) {
            if ($this->isOper($sender)) {
                $user = $this->user[$this->nick[$nick]];
                if ($user) {
                    if ($user['channels']) {
                        foreach ($user['channels'] as $chan => $status) {
                            if ($status == 'opped'|| $status == 'opvoiced') $opped .= ' '.$chan;
                            if ($status == 'voiced') $voiced .= ' '.$chan;
                            if ($status == 'regged') $regged .= ' '.$chan;
                        }
                    }

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Information about '.$user['nick'], 1);

                    $ctime = $this->timetodate($user['ctime']);

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Ident@Host:       '.$user['ident'].'@'.$user['host'], 1);
                    if ($user['sethost'])
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Vhost:            '.$user['sethost'], 1);
                    if ($user['auth'])
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Authed as:        '.$user['auth'], 1);

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Usermodes:        '.$user['modes'], 1);
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Numeric:          '.$user['numeric'], 1);
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :ip:               '.$this->IPbase64ToDecimal($user['ip']), 1);
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Connected at:     '.$ctime, 1);
                    if (!empty($opped))
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Opped in          '.trim($opped), 1);

                    if (!empty($voiced))
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Voiced in         '.trim($voiced), 1);

                    if (!empty($regged))
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Reg in            '.trim($regged), 1);

                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :End.', 1);
                }
                else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :No such user.', 1);
            }
            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use whois.', 1);
        }
        private function countusers($sender) {
            if ($this->isOper($sender)) {
                $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Total users:       '.count($this->user), 1);
                $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Total channels:    '.count($this->chan), 1);
                $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Total auths in db: '.count($this->auth), 1);
            }
            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use stats.', 1);
        }

        private function userlist($sender) {
            if ($this->isOper($sender)) {
                $spaces = '                      ';
                $line1 = 'Nick';
                $line2 = 'Status';
                $line3 = 'Auth';
                $line4 = 'Usermodes';
                $text .= substr_replace($spaces, $line1, 0, strlen($line1));
                $text .= substr_replace($spaces, $line2, 0, strlen($line2));
                $text .= substr_replace($spaces, $line3, 0, strlen($line3));
                $text .= $line4;
                $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :'.$text, 1);
                foreach ($this->user as $user => $info) {
                    $nick = $info['nick'];
                    $auth = $info['auth'];
                    $modes = $info['modes'];
                    if (!$auth) $auth = $spaces;
                    else $auth .= ' ';
                    $xtra = $spaces;
                    if ($this->isOflag($user)) $xtra = 'is an IRC Operator';
                    elseif ($this->isKflag($user)) $xtra = 'is a Network Service';
                    if ($xtra != $spaces) $xtra = substr_replace($spaces, $xtra, 0, strlen($xtra));
                    $cur = substr_replace($spaces, $nick, 0, strlen($nick));
                    $cur .= substr_replace($spaces, $xtra, 0, strlen($xtra));
                    $cur .= substr_replace($spaces, $auth, 0, strlen($auth));
                    $cur .= $modes;
                    $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' : '.$cur, 1);
                }
                $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :End of list.', 1);
            }
            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :You do not have sufficient privileges to use userlist.', 1);
        }

        private function getChannelinfo($sender, $chan) {
            if (isset($this->chan[$chan]['cdate'])) {
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Information about     '.$chan, 1);
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Chanmodes:            '.$this->chan[$chan]['modes'], 1);
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Created:              '.$this->timetodate($this->chan[$chan]['cdate']), 1);
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Channelkey:           '.$this->chan[$chan]['key'], 1);
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Channellimit:         '.$this->chan[$chan]['limit'], 1);
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Topic:                '.$this->chan[$chan]['topic'], 1);
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Users on the channel: ', 1);
            /*foreach ($this->user as $users => $m00) {
                if ($m00['channels'][$chan] == 'opped' || $m00['channels'][$chan] == 'opvoiced') {
                    $opped .= '@'.$m00['nick'].' ';
                    print_r($this->user[$users]); print '('.$users.')';
                }
                if ($m00['channels'][$chan] == 'voiced') $voiced .= '+'.$m00['nick'].' ';
                if ($m00['channels'][$chan] == 'regged') $regged .= $m00['nick'].' ';
            }*/
            foreach ($this->chan[$chan]['users'] as $user => $status) {
                //print 'chan: '.$chan.' '.$this->user[$user]['nick'].' is '.$status."($user])\n";
                if ($status == 'opped' || $status == 'opvoiced') {
                    $opped .= '@'.$this->user[$user]['nick'].' ';
                    //print_r($this->user[$users]); print '('.$users.')';
                }
                if ($status == 'voiced') $voiced .= '+'.$this->user[$user]['nick'].' ';
                if ($status == 'regged') $regged .= $this->user[$user]['nick'].' ';
            }
            if (!empty($opped)) $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :'.$opped, 1);
            if (!empty($voiced)) $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :'.$voiced, 1);
            if (!empty($regged)) $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :'.$regged, 1);
            if (count($this->chan[$chan]['bans']) >= 1) {
                $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :Channel bans: ', 1);
                foreach ($this->chan[$chan]['bans'] as $m00 => $test) {
                    if ($test) $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :'.$test, 1);
                }
            }
            $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :End.', 1);
            }
            else $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :No such channel.', 1);
        }

        private function showChannels($sender) {
            foreach ($this->chan as $chan => $mee) {
                $var = count($this->chan[$chan]['users']);
                $count[$var.' '.$chan] = "$chan - $var";
            }
            sort($count);
            foreach ($count as $channel)
                $this->SendRaw($this->bots['ms']['snumeric'].' O '.$sender.' :'.$channel,0);
        }

        private function joinChannels() {
            $this->db->query("
                SELECT *
                FROM channels
            ");
            while ($row = $this->db->fetch_assoc()) {
                $channel = strtolower($row['name']);
                $this->schan[$channel][strtolower($row['owner'])]['flags'] = strtolower($row['flags']);
                $this->schan[$channel][strtolower($row['owner'])]['topic'] = $row['topic'];
                if (is_numeric(strpos($row['flags'], 'c')) && !$row['suspended']) {
                    $autolimit = $row['autolimit'];
                    if ($autolimit) {
                        $time = time();
                        $this->lchan[$channel] = $time += 120;
                    }
                }
                if ($row['autolimit'])
                    $this->schan[$channel][strtolower($row['owner'])]['autolimit'] = $row['autolimit'];

                if ((strtolower($row['owner']) == 'c' && is_numeric(strpos($this->schan[$channel]['c']['flags'], 'j'))) && (!$row['suspended'])) {
                    $this->joinService($row['name'], strtolower($row['owner']));
                }
                elseif (strtolower($row['owner']) != 'c')
                    $this->joinService($row['name'], strtolower($row['owner']));

                if ($row['suspended']) {
                    $this->schan[$channel][strtolower($row['owner'])]['suspended'] = $row['suspended'];
                }
                $this->schan[$channel][strtolower($row['owner'])]['moo'] = 'mo';
            }
        }
        private function joinService($channel, $service) {
            $this->SendRaw($this->bots[$service]['snumeric'].' J '.$channel, 1);
            if ($service != 'google') {
                $this->SendRaw($this->config['numeric'].' M '.$channel.' +o '.$this->bots[$service]['snumeric'], 1);
                $this->setStatus($this->bots[$service]['snumeric'], $channel, 'opped');
            }
            else $this->setStatus($this->bots[$service]['snumeric'], $channel, 'regged');
        }

        private function getVersions() {
            $this->db->query("
                SELECT *
                FROM versions
            ");
            while ($row = $this->db->fetch_assoc()) {
                if (is_array($this->versions[$row['ip']])) {
                    foreach ($this->versions[$row['ip']] as $moo => $mee) {
                        if ($mee == $row['version']) $nvm = true;
                    }
                }
                if (!$nvm) $this->versions[$row['ip']][] = $row['version'];
                unset($nvm);
            }
        }

        private function killUser($numeric, $reason) {
            $this->SendRaw($this->bots['c']['snumeric'].' D '.$numeric.' :'.$this->config['snumeric'].' ('.$reason.')', 1);
            $sending[] = $numeric;
            $sending[] = 'Q';
            $sending[] = ':'.$reason;
            $this->quitUser($sending);
        }

        private function saveUsers($args) {
            if (isset($args[4])) {
                $server = $args[0];
                $nick = $args[2];
                if ($args[4] == 0) $connectedtime = time();
                else $connectedtime = $args[4];
                $ident = $args[5];
                $host = $args[6];
                if (strpos($args[7], '+') === 0) {
                  $modes = $args[7];
                  $num = $args[9];
                  $ip = $args[8];
                }
                else {
                  $modes = '+';
                  $num = $args[8];
                  $ip = $args[7];
                }
                $sethost = 0;
                $auth = 0;
                if (strpos($modes, 'h') && strpos($modes, 'r')) {
                  $sethost = $args[9];
                  $auth = $args[8];
                  $num = $args[11];
                  $ip = $args[10];
                }
                if (strpos($modes, 'r') && strpos($modes, 'h') === false) {
                  $auth = $args[8];
                  $num = $args[10];
                  $ip = $args[9];
                }
                if (strpos($modes, 'r') === false && strpos($modes, 'h')) {
                  $sethost = $args[8];
                  $num = $args[10];
                  $ip = $args[9];
                }

                /*
                if (strlen($args[2]) == 2 && is_numeric(strpos($args[2], 'Q')) && !$this->isOper($args[0])) {
                    $this->killUser($num, 'No, You\'re not a service.');
                    return;
                }
                */

                if ($auth && is_numeric(strpos($auth, ':'))) {
                    for ($i = 0; $i <= strlen($auth); $i++) {
                        $cur = $auth{$i};
                        if ($cur == ':') break;
                        else $tussenauth .= $cur;
                    }
                    $auth = $tussenauth;
                }

                $this->user[$num] = array(
                    'server' => $server,
                    'numeric' => $num,
                    'ctime' => $connectedtime,
                    'ident' => $ident,
                    'host' => $host,
                    'modes' => $modes,
                    'sethost' => $sethost,
                    'ip' => $ip,
                    'auth' => $auth
                );
                $this->setNick($num, $nick);
                if ($auth) $this->auth[strtolower($auth)]['users'][] = $num;

                foreach ($this->server as $server2 => $moo) {
                    if (is_array($moo)) {
                        foreach (array_keys($moo) as $key) {
                            if ($key == $server) {
                                if ($this->server[$server2][$server]['eb'])
                                    $nserver = $this->server[$server2][$server]['name'];
                            }
                        }
                    }
                }

                if (isset($nserver)) {
                    $this->SendRaw($this->bots['ms']['snumeric'].' P '.$this->config['debug_channel'].' :user ['.$nick.'] ['.$ident.'@'.$host.'] has connected on ['.$nserver.']', 1);
                    $this->SendRaw($this->bots['s']['snumeric'].' P '.$num.' :VERSION', 1);
                    $this->versionallow[$ip] = true;
                }
            }
            else {
                /*
                if (strlen($args[2]) == 2 && is_numeric(strpos($args[2], 'Q')) && !$this->isOper($args[0])) {
                    $this->killUser($args[0], 'No, You\'re not a service.');
                }
                */
                $this->setNick($args[0], $args[2]);
            }
        }

        private function setNick($numeric, $newnick) {
            $oldnick = $this->user[$numeric]['nick'];
            if ($oldnick) unset($this->nick[strtolower($oldnick)]);
            $this->nick[strtolower($newnick)] = $numeric;
            $this->user[$numeric]['nick'] = $newnick;
        }

        private function parseBan($ban, $chan) {
            if (!empty($ban)) {
                $chan = strtolower($chan);
                if (strpos($ban, ':%') === 0) $ban = str_replace(':%', '', $ban);
                $this->chan[$chan]['bans'][] = $ban;
            }
        }

        private function saveChannels($argz) {

            foreach ($argz as $arg) {
                $args[] = trim($arg);
            }

            if (strpos($args[4], ':') === 0) {
                $aantal = count($args);
                for ($i = 4; $i <= $aantal; $i++) {
                    $ban = $args[$i];
                    if (is_numeric(strpos($ban, '@')))
                        $bans[] = $ban;
                }
                $mauw = true;
            }


            $chan = strtolower(trim($args[2]));

            if (is_array($this->chan[$chan]['users'])) {
                if (array_count_values($this->chan[$chan]['users']) > 0) {
                    //print_r($this->chan[$args[2]]['users']);
                    $channel = true;
                }
            }


            foreach ($this->server as $server2 => $moo) {
                if (is_array($moo)) {
                    foreach (array_keys($moo) as $key2) {
                        if ($key2 == $server) {
                            if ($this->server[$server2][$server]['eb'])
                                $nserver = $this->server[$server2][$server]['name'];
                        }
                    }
                }
            }

            $check = true;

            if ($channel) { // channel already exists (so probably another server is connecting)
                if ($this->chan[$chan]['cdate'] < $args[3]) { // the new server is introducing a channel wich was created earlyer then ours, so known channel + user information will be resetted.
                    $reset = true;
                    print 'resetted '.$args[2];
                    $flags = $this->schan[$chan]['c']['flags'];
                    unset($this->chan[$chan]);
                    if ($flags) $this->schan[$chan]['c']['flags'] = $flags;
                    foreach ($this->user as $users => $m00) {
                        $bot = $this->numtoBot($m00['num']);
                        if ($bot && $bot != 'google') {
                            $this->SendRaw($this->config['numeric'].' M '.$chan.' +o '.$this->bots[$bot]['snumeric'], 1);
                            $this->setStatus($this->bots[$service]['snumeric'], $chan, 'opped');
                        }
                        elseif ($m00['channels'][$chan])
                            $this->setStatus($m00['numeric'], $chan, 'regged');
                    }
                }
                else $check = false;
            }

            if (!$mauw) {
            if (strpos($args[4], '+') === 0) {
                $modes = str_replace('+', '', $args[4]);
                $tlimit = strpos($args[4], 'l');
                $tkey = strpos($args[4], 'k');
                if (is_numeric($tkey) && is_numeric($tlimit)) {
                    $users = $args[7];
                    if (is_integer(strpos($args[8], ':%'))) {
                        $aantal = count($args);
                        for ($i = 8; $i <= $aantal; $i++) {
                            $ban = $args[$i];
                            if (is_numeric(strpos($ban, '@')))
                                $bans[] = $ban;

                        }
                    }
                    if ($tlimit < $tkey) {
                        $key = $args[6];
                        $limit = $args[5];
                    }
                    if ($tlimit > $tkey) {
                        $key = $args[5];
                        $limit = $args[6];
                    }
                }
                elseif (is_numeric(strpos($args[4], 'l')) && strpos($args[4], 'k') === false) {
                    $limit = $args[5];
                    $users = $args[6];
                    if (is_integer(strpos($args[7], ':%'))) {
                        $aantal = count($args);
                        for ($i = 7; $i <= $aantal; $i++) {
                            $ban = $args[$i];
                            if (is_numeric(strpos($ban, '@')))
                                $bans[] = $ban;
                        }
                    }
                }
                elseif (strpos($args[4], 'l') === false && is_numeric(strpos($args[4], 'k'))) {
                    $key = $args[5];
                    $users = $args[6];
                    if (is_integer(strpos($args[7], ':%'))) {
                        $aantal = count($args);
                        for ($i = 7; $i <= $aantal; $i++) {
                            $ban = $args[$i];
                            if (is_numeric(strpos($ban, '@')))
                                $bans[] = $ban;
                        }
                    }
                }
                else {
                    $users = $args[5];
                    if (is_integer(strpos($args[6], ':%'))) {
                        $aantal = count($args);
                        for ($i = 6; $i <= $aantal; $i++) {
                            $ban = $args[$i];
                            if (is_numeric(strpos($ban, '@')))
                                $bans[] = $ban;
                        }
                    }
                }
            }
            else {
                $modes = '+';
                $users = $args[4];
                    if (is_integer(strpos($args[5], ':%'))) {
                        $aantal = count($args);
                        for ($i = 5; $i <= $aantal; $i++) {
                            $ban = $args[$i];
                            if (is_numeric(strpos($ban, '@')))
                                $bans[] = $ban;
                        }
                    }
            }
            $args[2] = strtolower($args[2]);
            $flags = $this->schan[$args[2]]['c']['flags'];

            if ($check == true) {
                $this->chan[$args[2]] = array(
                    'cdate' => $args[3],
                    'modes' => $modes,
                    'limit' => $limit,
                    'key' => $key,
                    'flags' => $flags,
                );
            }
            $loop = strlen($users) + 2;
            for ($i = 0; $i <= $loop; $i++) {
                $cur = $users{$i};
                if ($cur == ',' || $i == $loop)  {
                    unset ($startstate);
                    if (!$state) {
                        $statesay = 'regged';
                    }
                    if ($state == ':ov') $statesay = 'opvoiced';
                    if ($state == ':o') $statesay = 'opped';
                    if ($state == ':v') $statesay = 'voiced';
                    if ($statesay == 'opped' || $statesay == 'opvoiced' || $statesay == 'voiced' || ($statesay == 'regged' || $reset)) {
                        $this->setStatus($curnum, $args[2], $statesay);
                        //print "\n\n".$this->user[$curnum]['nick']." ".$args[2]." $startstate\n\n";
                    }
                    unset($curnum);
                }
                else unset($endnum);

                if ($cur == ':') {
                  $startstate = 'on';
                  unset($state);
                }

                if ($startstate) {
                  $state .= $cur;
                }

                if ($cur != ',' && !$startstate) {
                  $curnum .= $cur;
                }
            }
            if ($bans) {
                $i = 0;
                foreach ($bans as $maa) {
                    if (is_numeric(strpos($maa,'@'))) {
                        if ($i == 0) $maa = str_replace(':%', '', $maa);
                        $i++;
                        $this->parseBan($maa, trim($args[2]));
                    }
                }
            }
            }
        }

    private function EA() {
    	/* End of Burst received */
    	/* [get] AB EB */
    	if (empty($this->EB)) {
    		$tmp = sprintf('%s EA',$this->config['numeric']);
    		$this->SendRaw($tmp,1);
    		$this->EB = TRUE;
    	}
    }

    private function leaveService($service, $channel) {
        /*$sends[] = $this->bots[$service]['snumeric'];
        $sends[] = 'L';
        $sends[] = $channel;
        $this->leaveChan($sends);*/
        $this->removeUserChannels($this->bots[$service]['snumeric'], $channel);
    }

    private function Pong($Args) {

	    // parting known empty channels //

        foreach ($this->schan as $chan => $info) {
            $count = count($this->chan[$chan]['users']);
            foreach (array_keys($info) as $key) {
                if ($count == 1) {
                    if ($key == 'c' || $key == 'google') {

                        if (($key == 'c' && $this->user[$this->bots[$key]['snumeric']]['channels'][$chan]) || ($key == 'google')) {
                            $this->SendRaw($this->bots[$key]['snumeric'].' L '.$chan, 1);
                            $this->leaveService($key, $chan);
                        }

                        if ($key == 'google')
                            unset($this->schan[strtolower($chan)][$key]);

                        $this->leaveService($this->bots[$key]['snumeric'], $chan);
                        $this->removeUserChannels($this->bots[$key]['snumeric'], $chan);
                    }
                }
            }
        }

	    // paring end //

	    // Channel limit //

	    if ($this->lchan) {
	        foreach ($this->lchan as $channel => $time) {
	            $tijd = time();
	            if ($time <= $tijd) {
	                $this->setLimit($channel);
	            }
	        }
	    }

	    // limit end //



		/* The server pinged us, we have to pong him back */
		/* [get] AB G !1061145822.928732 fish.go.moh.yes 1061145822.928732 */
		$this->Counter++;
		if ($this->Counter >= $this->PingPongs) {
			//$this->SaveChannels();
			$this->Counter=0; /* Putting it on zer0, for a new save/count */
		}
		$tmp = sprintf('%s Z %s',$this->config['numeric'],$Args[2]);
		$this->SendRaw($tmp,0);
		if ($this->DeBug) {
			printf("Ping Pong?!\n");
			@ob_flush();
		}
	}

    private function timer($i, $todo) {
        $tijd = time();
        while ($tijd != $i) {
            $tijd = time();
            if ($i == $time) $todo;
        }
    }


    private function SendRaw($Line,$Show) {
    /* This sends information to the server */
        /*if ($this->DeBug && $Show) {
        printf("[send]: %s\n",$Line);
        @ob_flush();
    }*/
    fputs($this->Socket,$Line."\r\n");
    printf("[send]: %s\n",$Line);
    }


    public function timetodate($time)
    {
        $date  = date("w j n Y G i", $time);
        $t     = time() - 60*60*24*3 ;
        $date  = explode (" ", $date);
        $date[0] = $dagen[$date[0]];
        $maand = $date[2];
        $date[2] = $maanden[$date[2]];
        if ($date[1] < 10) $date[1] = "0".$date[1];
        if ($maand < 10) $maand = "0".$maand;
        $date    = $date[1]."-".$maand."-".$date[3];
    return $date;
    }

    public function Return_Substrings($text, $sopener, $scloser) {
        $result = array();

        $noresult = substr_count($text, $sopener);
        $ncresult = substr_count($text, $scloser);

        if ($noresult < $ncresult)
               $nresult = $noresult;
        else
               $nresult = $ncresult;

        unset($noresult);
        unset($ncresult);

        for ($i=0;$i<$nresult;$i++)
               {
               $pos = strpos($text, $sopener) + strlen($sopener);

               $text = substr($text, $pos, strlen($text));

               $pos = strpos($text, $scloser);

               $result[] = substr($text, 0, $pos);

               $text = substr($text, $pos + strlen($scloser), strlen($text));
               }

        return $result;
    }

    private function IPbase64ToDecimal($base64)
    {
        $ip = $this->base64Todecimal($base64);
        $iphex = dechex($ip);
        $part0 = substr($iphex,0,2);
        $part0 = hexdec($part0);

        $part1 = substr($iphex,2,2);
        $part1 = hexdec($part1);

        $part2 = substr($iphex,4,2);
        $part2 = hexdec($part2);

        $part3 = substr($iphex,6,2);
        $part3 = hexdec($part3);
        return $part0 . "." . $part1 . "." . $part2 . "." . $part3;
    }


    private function base64Todecimal($Base64)
    {
        $b64chars = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
        'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b',
        'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
        'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3',
        '4', '5', '6', '7', '8', '9', '[', ']');
        for ($i = 0; $i < strlen($Base64); $i++)
        {
            $cur = $Base64{$i};
            for ($j = 0; $j < 64; $j++)
                if ($b64chars[$j] == $cur) $num = $j;
            $pow = strlen($Base64) - $i - 1;
            $pow = pow(64,$pow);
            $result += $num * $pow;
        }
        return $result;
    }

    private function tomirc($text) {
        $text = str_replace('<b>', '', $text);
        $text = str_replace('</b>', '', $text);
        $text = str_replace('<u>', '', $text);
        $text = str_replace('</u>', '', $text);
        $text = str_replace('<em>', '', $text);
        $text = str_replace('</em>', '', $text);
        return $text;
    }
}
