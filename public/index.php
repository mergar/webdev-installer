<?php
define('CBSD_CMD','env NOCOLOR=1 /usr/local/bin/sudo /usr/local/bin/cbsd ');
$product="WebDev";

/*
 	[HTTP_ACCEPT] => application/json, text/javascript
	[HTTP_X_REQUESTED_WITH] => XMLHttpRequest
 */
if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest')
{
	if($_SERVER['REQUEST_METHOD']=='POST')
	{
		if(isset($_POST['iface']))
		{
			$mode=$_POST['mode'];
			switch($mode)
			{
				case 'getNetInfo':
					updateNetInfo($_POST);
					echo json_encode(getNetworkInfo($_POST['iface']));
					break;
				case 'checkDHCP':
					checkDHCP($_POST['iface']);
					echo json_encode(getNetworkInfo($_POST['iface']));
					break;
				case 'PWGen':
					include('pwgen.class.php');
					$pw=new PWGen;
					$pw=$pw->generate();
					echo json_encode(array('password'=>$pw));
					break;
				case 'startInstall':
					startInstall($_POST);
					break;
				case 'reboot':
					reboot();
					break;
			}
		}
	}

exit;
}

function cbsd_cmd($cmd)
{
	$descriptorspec = array(
		0 => array('pipe','r'),
		1 => array('pipe','w'),
		2 => array('pipe','r')
	);
//echo self::CBSD_CMD.$cmd;exit;
	$process = proc_open(CBSD_CMD.$cmd,$descriptorspec,$pipes,null,null);

	$error=false;
	$error_message='';
	$message='';
	if (is_resource($process))
	{
		$buf=stream_get_contents($pipes[1]);
		$buf0=stream_get_contents($pipes[0]);
		$buf1=stream_get_contents($pipes[2]);
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$task_id=-1;
		$return_value = proc_close($process);
		if($return_value!=0)
		{
			$error=true;
			$error_message=$buf;
		}else{
			$message=trim($buf);
		}
		
		return array('cmd'=>$cmd,'retval'=>$return_value, 'message'=>$message, 'error'=>$error,'error_message'=>$error_message);
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<!--[if lt IE 9]><script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script><![endif]-->
	<title><?php echo "$product"; ?></title>
	<link rel="icon" type="image/x-icon" href="/images/favicon.ico" />
	<meta name="keywords" content="" />
	<meta name="description" content="" />
	<link href="/css/screen.css" rel="stylesheet" media="(min-width: 800px)" />
	<link href="/css/lowres.css" rel="stylesheet" media="(max-width: 800px)" />
	<script type="text/javascript" src="/js/jquery.js"></script>
	<script type="text/javascript" src="/js/main.js"></script>
</head>

<body>
<noscript>
	<p>Please, turn on a Javascript!</p>
	<div style="display:none;">
</noscript>
<div class="wrapper">

	<header class="header">
		<span class="proj-name">CBSD <span style="font-size:40%;">WebDev Installer</span></span>
	</header><!-- .header-->

	<main class="content"> <!-- высоту этого элемента нужно вычислять в JS -->
		<div id="content"> <!-- высоту и ширину (сумма всех ширин) этого элемента нужно вычислять в JS -->
			<div class="wiz-page p1"> <!-- ширину (на ширину экрана) этого элемента нужно вычислять в JS -->

				<div class="inf-area hdd"><div class="inf-area-cnt">
					<h1>Select hard disk:</h1>
<?php
//$myCMD="su web -c 'sudo /usr/local/bin/cbsd disks-list md=1'";
$myCMD=CBSD_CMD." disks-list";	// md=1
$res=cbsd_cmd($myCMD);
//showItemsList($arr,'disk');
if($res['retval']==0)
{
	$buf=$res['message'];
	$arr=explode(PHP_EOL,trim($buf));
	if(!empty($arr))
	{
		echo '					<ul class="i-list">',PHP_EOL;
		foreach($arr as $key=>$disk)
		{
			list($name,$vendor,$size)=explode(':',$disk);
			$name=trim($name);
			$vendor=trim($vendor);
			// удаляем < >  и прочую
			$vendor = str_replace( array( '\'', '"', ',' , ';', '<', '>' ), ' ', $vendor);
			if(strlen($vendor)<2) $vendor="Can't identify model";
			$size=fileSizeConvert(trim($size));
			echo '						<li><input type="checkbox" name="disk[]" id="disk-',$key,'" value="'.$name.'" /><label for="disk-',$key,'">',
			$name,' — ',$vendor,' (',$size,')</label></li>',PHP_EOL;
		}
		echo '					</ul>',PHP_EOL;
	}
	unset($arr);
}
?>
				</div></div>

				<div class="inf-area hr"></div>

				<div class="inf-area network"><div class="inf-area-cnt">
						<h1>Select network card:</h1>
<?php
// and show available nics on the system
$myCMD=CBSD_CMD." nics-list desc=1 skip=lo";
$res=cbsd_cmd($myCMD);

if($res['retval']==0)
{
	$buf=$res['message'];
	$arr=explode(PHP_EOL,trim($buf));
	# get net interface info
	/*
		nic: ${MYNIC}
		ip: ${ip}
		gw: ${gw}
		mask: ${mask}
		${MYDHCP}
	 */
	if(!empty($arr))
	{
		echo '					<ul class="i-list"><form id="net-form">',PHP_EOL;
		$checked=true;
		foreach($arr as $key=>$card)
		{
			list($name,$vendor,$updn)=explode(':',$card);
			$name=trim($name);
			$vendor=trim($vendor);
			$updn=trim($updn);

			$info=getNetworkInfo($name);
			//print_r($info);

			$chk=$dhcp=$ip4=$gw4=$mask4=$ip6=$gw6=$mask6=$lichk=$nic='';
			$disabled=' disabled="disabled"';
			$disabledc=' disabled';
			if(isset($info['nic']))
			{
				if($info['nic']==$name)
				{
					$nic=$info['nic'];
					if($checked) $chk=' checked="checked"';
					if($checked) $lichk=' checked';
					$checked=false;
					$ip4=isset($info['ip4'])?$info['ip4']:'';
					$gw4=isset($info['gw4'])?$info['gw4']:'';
					$mask4=isset($info['mask4'])?$info['mask4']:'';
					$ip6=isset($info['ip6'])?$info['ip6']:'';
					$gw6=isset($info['gw6'])?$info['gw6']:'';
					$mask6=isset($info['mask6'])?$info['mask6']:'';
					$dhcp=(isset($info['dhcpd']) && trim($info['dhcpd'])=='YES')?' checked="checked"':'';
					$disabled='';
					$disabledc='';
				}
			}
			echo '						<li class="card-',$name,'"><input type="radio" name="card" class="netcard" id="card-',$key,'"',$chk,' /><label for="card-',$key,'">',
			$name,' — ',$vendor,' (',$updn,')</label>',PHP_EOL;
			echo '							<fieldset class="hide',$disabledc,$lichk,'" id="inf-',$name,'">
												<legend>Network settings</legend>
												<label class="net">Use DHCP:</label> <input type="checkbox" class="dhcpd" name="dhcpd"',$dhcp,$disabled,' />
												<input type="button" value="Check DHCP" onclick="network.check(\'',$name,'\')" style="margin-left:10px;" />
												<span class="dhcp-errmsg"></span><br />
												<div>
													<div class="fleft">
														<p><label class="net">IP v6:</label> <input type="text" name="network[',$name,'][ip6]" value="',$ip6,'"',$disabled,' class="ip6" /></p>
														<p><label class="net">Gateway v6:</label> <input type="text" name="network[',$name,'][gw6]" value="',$gw6,'"',$disabled,' /></p>
														<p><label class="net">Mask v6:</label> <input type="text" name="network[',$name,'][mask6]" value="',$mask6,'"',$disabled,' /></p>
													</div>
													<p><label class="net">IP v4:</label> <input type="text" name="network[',$name,'][ip4]" value="',$ip4,'"',$disabled,' class="ip4" /></p>
													<p><label class="net">Gateway v4:</label> <input type="text" name="network[',$name,'][gw4]" value="',$gw4,'"',$disabled,' /></p>
													<p><label class="net">Mask v4:</label> <input type="text" name="network[',$name,'][mask4]" value="',$mask4,'"',$disabled,' /></p>
												</div>
											</fieldset></li>',PHP_EOL;
		}
		echo '					</form></ul>',PHP_EOL;
		unset($arr);
	}
}
?>
				</div></div>

				<div class="inf-area hr"></div>

				<div class="inf-area locale"><div class="inf-area-cnt">
						<h1>Select timezone:</h1>
						<div class="bquot">
							<input type="text" name="timezone" class="timezones-inp" id="tzsel" autocomplete="off" />
							<div class="small-text">start type here your timezone or select it into widget below&hellip;</div>
<div class="timezones">
	<div class="letters"> <span class="towns-txt clean">towns:</span>
		<span class="n1 lt">A</span><span class="n2 lt">B</span><span class="n3 lt">C</span><span class="n4 lt">D
		</span><span class="n5 lt">E</span><span class="n6 lt">F</span><span class="n7 lt">G</span><span class="n8 lt">
		H</span><span class="n9 lt">I</span><span class="n10 lt">J</span><span class="n11 lt">K</span><span class="n12 lt">
		L</span><span class="n13 lt">M</span><span class="n14 lt">N</span><span class="n15 lt">O</span><span class="n16 lt">
		P</span><span class="n17 lt">Q</span><span class="n18 lt">R</span><span class="n19 lt">S</span><span class="n20 lt">
		T</span><span class="n21 lt">U</span><span class="n22 lt">V</span><span class="n23 lt">W</span><span class="n24 lt">
		X</span><span class="n25 lt">Y</span><span class="n26 lt">Z</span>
	</div>
	<div class="timezones-cnt">
		<div class="left">
			<ul class="regions">
				<li class="africa sel">Africa</li>
				<li class="america">America</li>
				<li class="asia">Asia</li>
				<li class="atlantic">Atlantic</li>
				<li class="australia">Australia</li>
				<li class="europe">Europe</li>
				<li class="indian">Indian</li>
				<li class="pacific">Pacific</li>
				<li class="etc">Other</li>
				<li class="clean">&nbsp;</li>
				<li class="antarctica">Antarctica</li>
				<li class="arctic">Arctic</li>
			</ul>
		</div>
		<div class="right">
			<div class="tmz cnt"><ul id="ul-timezone" class="tmz africa">
<?php
$file='conf/timezonedb.txt';
$file_html='conf/timezonedb_html.txt';
//unlink($file_html);	//exit;
$letters='abcdefghijklmnopqrstuvwxyz';
if(file_exists($file_html))
{
	echo file_get_contents($file_html);
}else{
	$buf=file($file);
	$arr=array();
	foreach($buf as $f) {$arr[]=$f;}
	sort($arr,SORT_STRING);
	$json=json_encode($arr);
	file_put_contents('tmz.json',$json);
	unset($json);
	$html='';
	foreach($arr as $val)
	{
		$town1='';
		@list($region,$town,$town1)=explode('/',trim($val));
		$region=strtolower($region);
		if($town1!='')
		{
			$letter=substr(strtolower($town1),0,1);
			$town.='/'.$town1;
		}else{
			$letter=substr(strtolower($town),0,1);
		}
		$pos=strpos($letters,$letter);
		if($pos!==false) { $letter='n'.($pos+1); }else{ $letter='n100'; }
		$html.='				<li class="town '.$region.' '.$letter.'">'.$town.'</li>'.PHP_EOL;
	}
	file_put_contents($file_html,$html);
	echo $html;
	unset($arr);
}
?>
<!--				<li class="africa n1">Abidjan</li> -->
			</ul></div>
			<div class="hr"></div>
			<div class="pages-pill">
				<span class="page-pill sel"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
				<span class="page-pill"></span>
			</div>
		</div>
		<div class="clear"></div>
	</div>
</div>

						</div>
					</div></div>

			<div class="inf-area hr"></div>

			<div class="inf-area auth"><div class="inf-area-cnt">
				<h1>Select password for user «root»:</h1>
				<div class="bquot">
					<p>Type your password or <span class="genpass">click here for generate random</span>:</p>
					<p><input type="text" name="password" class="timezones-inp" id="pass" /></p>
					<p>Repeat:</p>
					<p><input type="text" name="password_repeat" class="timezones-inp" id="pass_rep" /></p>
				</div>
			</div></div>

			<span class="yelbut install">INSTALL</span>

			</div>
			<div class="wiz-page p2">
				<div id="progress-area">
					<div class="icons">
						<span class="ico fs"><span class="txt">File System</span></span>
						<span class="ico os"><span class="txt">Operation System</span></span>
						<span class="ico pkg"><span class="txt">Packages</span></span>
						<span class="ico cfg"><span class="txt">Configuration</span></span>
					</div>
					<div id="progress-bar">
						<span class="expand"></span>
					</div>
					<div id="percent-txt">0%</div>
					<div class="lg-color"><span id="time-txt">0:0:0</span> <span class="xsmall-text">(if the timer is stopped, OS is not installed)</span></div>
					<p>&nbsp;</p>
					<p>&nbsp;</p>
					<p>WebDev is now being installed. This process may take a while, depending upon the system speed. You will be notified when the installation is finished.</p>
				</div>
			</div>
			<div class="wiz-page p3">
				<div class="inf-area text-center">
					<h1>WebDev is now installed!</h1>
					<p>Click Finish to reboot. Once the reboot is complete, WebDev will be available via http://{IP}</p>
					<div class="hr"></div>
					<p>If the system are not available, the interface to change network settings will be available on the server via GUI console.</p>
				</div>
				<span class="yelbut reboot">REBOOT</span>
			</div>
		</div>
	</main><!-- .content -->

</div><!-- .wrapper -->
<noscript>
	</div> <!-- noscript div -->
</noscript>
<footer class="footer">
	<div class="footer-cnt">
		<span onclick="wizard.prevPage()" style="color:white;font-size:large;cursor:pointer;"> « </span>
		<span onclick="wizard.nextPage()" style="color:white;font-size:large;cursor:pointer;"> » </span>
		<br /><span class="copyright">&copy; Cloud Technologies. <?php echo date('Y',time()); ?></span>
	</div>
</footer><!-- .footer -->

<div class="wait-over"></div>
</body>
</html>
<?php

/**
 * Converts bytes into human readable file size.
 *
 * @param string $bytes
 * @return string human readable file size (2,87 Мб)
 * @author Mogilev Arseny
 */
function fileSizeConvert($bytes)
{
	$bytes = floatval($bytes);
	$arBytes = array(
		0 => array(
			"UNIT" => "TB",
			"VALUE" => pow(1024, 4)
		),
		1 => array(
			"UNIT" => "GB",
			"VALUE" => pow(1024, 3)
		),
		2 => array(
			"UNIT" => "MB",
			"VALUE" => pow(1024, 2)
		),
		3 => array(
			"UNIT" => "KB",
			"VALUE" => 1024
		),
		4 => array(
			"UNIT" => "B",
			"VALUE" => 1
		),
	);

	$result='';
	foreach($arBytes as $arItem)
	{
		if($bytes >= $arItem["VALUE"])
		{
			$result = $bytes / $arItem["VALUE"];
			$result = str_replace(".", "," , strval(round($result, 2)))." ".$arItem["UNIT"];
			break;
		}
	}
	return $result;
}

function checkDHCP($iface)
{
	$myCMD=CBSD_CMD." wb_netcfg mode=trydhcp nic=".$iface;
	$res=cbsd_cmd($myCMD);
	$buf=$res['message'];
}

function updateNetInfo($post)
{
	$keys=array('nic','ip4','mask4','gw4','ip6','mask6','gw6');
	$arr=array();
	$arr[]='nic="'.$post['iface'].'"';
	foreach($post as $key=>$val)
	{
		if(in_array($key,$keys))
		{
			if(!empty($val)) $arr[]=$key.'="'.$val.'"';
		}
	}
	$txt=join(' ',$arr);
	$myCMD=CBSD_CMD." wb_netcfg mode=update ".$txt;
	echo $myCMD;
	//$buf=htmlentities( shell_exec($myCMD) );
	$res=cbsd_cmd($myCMD);
	$buf=$res['message'];
	
	$arr=explode(PHP_EOL,trim($buf));
//	print_r($arr);
//	exit;
}

function getNetworkInfo($nic)
{
	$info=array();
	$fname='/tmp/networks.'.$nic.'.txt';
	if(file_exists($fname))
	{
		$buf=trim(file_get_contents($fname));
		$arr1=explode(PHP_EOL,$buf);
		$info=array();
		foreach($arr1 as $a)
		{
			list($key,$val)=explode(':',$a,2);
			$key=trim($key);
			$val=trim($val);
			if(strtolower($key)=='dhcpd' && strtolower($val)=='no')
				$info['errmsg']='DHCP is not possible!';
			else
				$info['errmsg']='DHCP is ok!';
			$info[$key]=$val;
		}
	}
//echo '<pre>',print_r($info,true),'</pre>';
	return $info;
}

function reboot()
{
	$cmd=CBSD_CMD.'wb_reboot';
	$res=run_cmd($cmd);

	echo json_encode(array(
		'error'=>false,
		'error_messages'=>''
	));
	exit;
}

function startInstall($posts)
{
/*
	[network] => Array
	(
		[xn0] => Array
			(
				[ip6] =>
				[gw6] =>
				[mask6] =>
				[ip4] => 199.48.133.74
				[gw4] => 199.48.133.73
				[mask4] => 255.255.255.252
			)

	)

 Array
(
	[dhcpd-xn0] => on
	[disk] => Array
	(
		[0] => ada0
	)
	[timezone] => Europe/Moscow
	[password] => eiquiJ8t
	[iface] => re0
	[mode] => startInstall
)
 */
	$errors=array();

#	TIMEZONE settings
	if(isset($posts['timezone']) && !empty($posts['timezone']))
	{
		$cmd=CBSD_CMD.'tzcfg set="'.$posts['timezone'].'"';
		$res=run_cmd($cmd);
		if($res['retval']!=0)
			$errors[]=$res['error_message'].'<span class="small-text">(cmd:'.$res['cmd'].')</span>';
//print_r($res);
	}

#	NETWORK settings
	if(isset($posts['dhcpd']) && strtolower($posts['dhcpd'])=='on')
	{
		$cmd=CBSD_CMD.'wb_netcfg mode=save nic="'.$posts['iface'].'" ip4="DHCP"';
		$res=run_cmd($cmd);
		if($res['retval']!=0)
			$errors[]=$res['error_message'].'<span class="small-text">(cmd:'.$res['cmd'].')</span>';
//print_r($res);
	}elseif(isset($posts['network'])){
		if(empty($posts['network']))
		{
			$errors[]='Network adapter is not select!';
		}else{
			$keys=array('nic','ip4','mask4','gw4','ip6','mask6','gw6');
			$arr=array();
			$network=$posts['network'][$posts['iface']];
			$arr[]='nic="'.$posts['iface'].'"';
			foreach($network as $key=>$val)
			{
				if(in_array($key,$keys))
				{
					if(!empty($val)) $arr[]=$key.'="'.$val.'"';
				}
			}
			$txt=join(' ',$arr);

			$cmd=CBSD_CMD.'wb_netcfg mode=save '.$txt;
			$res=run_cmd($cmd);
			if($res['retval']!=0)
				$errors[]=$res['error_message'].'<span class="small-text">(cmd:'.$res['cmd'].')</span>';
		}
//print_r($res);exit;
	}

#	PASSWORDS settings
	if(isset($posts['password']) && !empty($posts['password']))
	{
		$cmd=CBSD_CMD."wb_usercfg user=root pw='".$posts['password']."'";
		$res=run_cmd($cmd);
		if($res['retval']!=0)
			$errors[]=$res['error_message'].'<span class="small-text">(cmd:'.$res['cmd'].')</span>';
//print_r($res);

		$cmd=CBSD_CMD."wb_usercfg user=cbsd pw='".$posts['password']."'";
		$res=run_cmd($cmd);
		if($res['retval']!=0)
			$errors[]=$res['error_message'].'<span class="small-text">(cmd:'.$res['cmd'].')</span>';
//print_r($res);
	}

#	DISKS check
	if(!isset($posts['disk']) || empty($posts['disk']))
	{
		$errors[]='Disk for install is not selected!';
	}

#	INSTALLATION
	if(empty($errors))
	{
		$retval=install($posts);
		echo json_encode(array(
			'install_starting'=>($retval==0)
		));
		exit;
	}

//	print_r($posts);exit;
//	print_r($errors);
	$error=(count($errors)>0);
	$error_messages=join(PHP_EOL,$errors);
	echo json_encode(array(
		'error'=>$error,
		'error_messages'=>$error_messages
	));
	exit;
}

function install($posts)
{
	$txt=join(':',$posts['disk']);
	$cmd=CBSD_CMD.'wb_zfs_install dsk="'.$txt.'"';
	$res=run_cmd($cmd);
	return $res['retval'];
}

function run_cmd($cmd)
{
	$descriptorspec = array(
		0 => array('pipe','r'),
		1 => array('pipe','w'),
		2 => array('pipe','r')
	);

	$process = proc_open($cmd,$descriptorspec,$pipes,null,null);

	if (is_resource($process))
	{
		$buf=stream_get_contents($pipes[1]);
		fclose($pipes[0]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$return_value = proc_close($process);
		if($return_value!=0)
		{
			$error=true;
			$error_message=$buf;
		}else{
			$error=false;
			$error_message='';
		}
		return array('cmd'=>$cmd,'retval'=>$return_value,'error'=>$error,'error_message'=>$error_message);
	}
}
