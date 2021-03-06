<?PHP
function handle_messages(TeamSpeak3_Adapter_ServerQuery_Event $event, TeamSpeak3_Node_Host $host) {
	global $lang, $logpath, $timezone, $nextupinfo, $nextupinfomsg1, $nextupinfomsg2, $nextupinfomsg3, $mysqlcon, $dbname, $grouptime, $substridle, $slowmode, $currvers, $newversion, $adminuuid, $phpcommand;
	if($host->whoami()["client_unique_identifier"] != $event["invokeruid"]) {  //check whoami need to slowmode or is already stored?
	$uuid = $event["invokeruid"];
	
	
		if(strstr($event["msg"], 'nextup') && $nextupinfo != 0) {
			//enter_logfile($logpath,$timezone,6,"Client ".$event["invokername"]." (".$event["invokeruid"].") sent textmessage: ".$event["msg"]);
			if(($dbuserdata = $mysqlcon->query("SELECT count,nextup,idle,except FROM $dbname.user WHERE uuid='$uuid'")) === false) {
				enter_logfile($logpath,$timezone,2,"handle_messages 1:".print_r($mysqlcon->errorInfo(), true));
			}
			$user = $dbuserdata->fetchAll(PDO::FETCH_ASSOC);

			if(($dbgroups = $mysqlcon->query("SELECT * FROM $dbname.groups")) === false) {
				enter_logfile($logpath,$timezone,2,"handle_messages 2:".print_r($mysqlcon->errorInfo(), true));
			}
			$servergroups = $dbgroups->fetchAll(PDO::FETCH_ASSOC);
			foreach($servergroups as $servergroup) {
				$sqlhisgroup[$servergroup['sgid']] = $servergroup['sgidname'];
			}

			ksort($grouptime);
			$countgrp = count($grouptime);
			$grpcount=0;
			foreach ($grouptime as $time => $groupid) {
				if ($substridle == 1) {
					$nextup = $time - $user[0]['count'] + $user[0]['idle'];
				} else {
					$nextup = $time - $user[0]['count'];
				}
				$dtF = new DateTime("@0");
				$dtT = new DateTime("@$nextup");
				$days  = $dtF->diff($dtT)->format('%a');
				$hours = $dtF->diff($dtT)->format('%h');
				$mins  = $dtF->diff($dtT)->format('%i');
				$secs  = $dtF->diff($dtT)->format('%s');
				$grpcount++;
				if ($nextup > 0 && $nextup < $time || $grpcount == $countgrp && $nextup <= 0) {
					check_shutdown($timezone,$logpath); usleep($slowmode);
					if ($grpcount == $countgrp && $nextup <= 0) {
						try {
							$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message(sprintf($nextupinfomsg2, $days, $hours, $mins, $secs, $sqlhisgroup[$groupid]));
						} catch (Exception $e) {
							enter_logfile($logpath,$timezone,2,"handle_messages 3:".$e->getCode().': '.$e->getMessage());
						}
					} elseif ($user[0]['except'] == 2 || $user[0]['except'] == 3) {
						try {
							$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message(sprintf($nextupinfomsg3, $days, $hours, $mins, $secs, $sqlhisgroup[$groupid]));
						} catch (Exception $e) {
							enter_logfile($logpath,$timezone,2,"handle_messages 4:".$e->getCode().': '.$e->getMessage());
						}
					} else {
						try {
							$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message(sprintf($nextupinfomsg1, $days, $hours, $mins, $secs, $sqlhisgroup[$groupid]));
						} catch (Exception $e) {
							enter_logfile($logpath,$timezone,2,"handle_messages 5:".$e->getCode().': '.$e->getMessage());
						}
					}
					if($nextupinfo == 1) {
						break;
					}
				}
			}
		}
		
		if(strstr($event["msg"], 'version')) {
			if(version_compare(substr($newversion, 0, 5), substr($currvers, 0, 5), '>') && $newversion != '') {
				try {
					$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message(sprintf($lang['upmsg'], $currvers, $newversion));
				} catch (Exception $e) {
					enter_logfile($logpath,$timezone,2,"handle_messages 6:".$e->getCode().': '.$e->getMessage());
				}
			} else {
				try {
					$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message(sprintf($lang['msg0001'], $currvers));
				} catch (Exception $e) {
					enter_logfile($logpath,$timezone,2,"handle_messages 7:".$e->getCode().': '.$e->getMessage());
				}
			}
		}
		
		if(strstr($event["msg"], 'help') || strstr($event["msg"], 'info') || strstr($event["msg"], 'commands')) {
			try {
				$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message($lang['msg0002']);
			} catch (Exception $e) {
				enter_logfile($logpath,$timezone,2,"handle_messages 8:".$e->getCode().': '.$e->getMessage());
			}
		}
		
		if((strstr($event["msg"], 'shutdown') || strstr($event["msg"], 'quit') || strstr($event["msg"], 'stop')) && $event["invokeruid"] == $adminuuid) {
			enter_logfile($logpath,$timezone,5,sprintf($lang['msg0004'], $event["invokername"], $event["invokeruid"]));
			$path = substr(__DIR__, 0, -4);
			try {
				$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message($lang['msg0005']);
			} catch (Exception $e) {
				enter_logfile($logpath,$timezone,2,"handle_messages 9:".$e->getCode().': '.$e->getMessage());
			}
			exec($phpcommand." ".$path."worker.php stop");
		} elseif (strstr($event["msg"], 'shutdown') || strstr($event["msg"], 'exit')) {
			try {
				$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message($lang['msg0003']);
			} catch (Exception $e) {
				enter_logfile($logpath,$timezone,2,"handle_messages 10:".$e->getCode().': '.$e->getMessage());
			}
		}
		
		if((strstr($event["msg"], 'restart') || strstr($event["msg"], 'reboot')) && $event["invokeruid"] == $adminuuid) {
			enter_logfile($logpath,$timezone,5,sprintf($lang['msg0007'], $event["invokername"], $event["invokeruid"]));
			$path = substr(__DIR__, 0, -4);
			try {
				$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message($lang['msg0006']);
			} catch (Exception $e) {
				enter_logfile($logpath,$timezone,2,"handle_messages 11:".$e->getCode().': '.$e->getMessage());
			}
			if (substr(php_uname(), 0, 7) == "Windows") {
				exec("start ".$phpcommand." ".$path."worker.php restart");
			} else {
				exec($phpcommand." ".$path."worker.php restart > /dev/null 2>/dev/null &");
			}
		} elseif (strstr($event["msg"], 'shutdown') || strstr($event["msg"], 'exit')) {
			try {
				$host->serverGetSelected()->clientGetByUid($event["invokeruid"])->message($lang['msg0003']);
			} catch (Exception $e) {
				enter_logfile($logpath,$timezone,2,"handle_messages 12:".$e->getCode().': '.$e->getMessage());
			}
		}
		
	}
}
?>