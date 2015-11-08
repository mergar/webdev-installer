<?php
/**
 * Created by PhpStorm.
 * User: MOVe
 * Date: 18.02.14
 * Time: 22:33
 */

class Make extends Form
{
	public $tpl='';

	private $_is_post=false;
	private $_post=array();

	protected $_cbsd_cmd='env NOCOLOR=1 /usr/local/bin/sudo /usr/local/bin/cbsd ';

	function render()
	{
		/*
		$res=$this->run_cmd('wb_phpcfg mode=set file="/usr/home/web/webdev.bsdstore.ru/public/conf/php.ini.tpl.conf');
		echo '<pre>';print_r($res);
		exit;
		*/

		$this->startHTML();
		if(method_exists($this,'renderForm'))
		{
			#
			$this->renderForm();
		}
		$this->endHTML();

		echo $this->tpl;

		if($this->_is_post)
		{
			echo '<pre>';
			/*
			ob_start();
			highlight_file('conf/php.ini.tpl.php');
			//include($this->file_name.'.php');
			$buf=ob_get_contents();
			ob_clean();
			echo $buf;exit;
			file_put_contents($this->file_name.'.conf',$buf);
			echo 'hi!';
			//highlight_file('conf/php.ini.tpl.php');
			print_r( $this->updateConfig() );
			*/
			file_put_contents($this->file_name.'.conf',$this->getConfig());
			$res=$this->updateConfig();
			print_r($res);
			exit;
		}
	}

	function startHTML()
	{
		$this->tpl.='<html>'.PHP_EOL
			.'<head>'.PHP_EOL
			.'	<title>Form</title>'.PHP_EOL
			.'	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> '.PHP_EOL
			.'	<link rel="stylesheet" type="text/css" media="screen" href="/css/forms.css" />'.PHP_EOL
			.'</head>'.PHP_EOL
			.'<body>'.PHP_EOL;
	}

	function endHTML()
	{
		$this->tpl.='</body>'.PHP_EOL.'</html>';
	}

	function initVariables()
	{
		$file=$this->file_name.'.vars';
		if($_SERVER['REQUEST_METHOD']=='POST')
		{
			$this->_is_post=true;
			$this->_post=$_POST;
			if(!empty($_POST)) file_put_contents($file,json_encode($_POST));
		}else{
			if(file_exists($file))
			{
				$vars=json_decode(file_get_contents($file));
				if(!empty($vars))foreach($vars as $key=>$var)
				{
					$this->_post[$key]=$var;
				}
			}
		}

		if(!empty($this->form_elements))
			foreach($this->form_elements as $key=>&$el)
		{
			if(isset($this->_post[$key]))
			{
				$el['var']=$this->_post[$key];
			}elseif(isset($el['default']))
			{
				$el['var']=$el['default'];
			}
			//print_r($el);
		}
		//exit;
	}

	function updateConfig()
	{
		$file_name=realpath($this->file_name);

		if($this->isConfig('redis'))
			$cmd='wb_rediscfg mode=set file="'.$file_name.'.conf"'; // /tmp/new_redis.ini

		if($this->isConfig('php'))
			$cmd='wb_phpcfg mode=set file="'.$file_name.'.conf"'; // /tmp/new_php.ini

		if(empty($cmd))
		{
			echo 'Config command is not recognized!';
			exit;
		}
		return $this->run_cmd($cmd);
	}

	function isConfig($name)
	{
		if(strpos($this->file_name,$name)!==false)
			return true;
		return false;
	}

	function run_cmd($cmd)
	{
		$descriptorspec = array(
			0 => array('pipe','r'),
			1 => array('pipe','w'),
			2 => array('pipe','r')
		);

		$process = proc_open($this->_cbsd_cmd.$cmd,$descriptorspec,$pipes,null,null);

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
			return array('cmd'=>$this->_cbsd_cmd.$cmd,'retval'=>$return_value,'error'=>$error,'error_message'=>$error_message);
		}
	}
}

class Form
{
	private $element_num=1;

	function renderForm()
	{
		$this->tpl.='<div style="width:400px;">';
		$this->tpl.='<fieldset><p class="form_top">PHP variables:</p><form action="" method="post"><div class="form_cnt">'.PHP_EOL;

		$n=0;
		if(!empty($this->form_elements))foreach($this->form_elements as $key=>$item)
		{
			if($n++ < 3) $comment='<span class="comment">тестируем комментарии под полем ввода параметров&hellip;</span>'; else $comment='';
			$this->tpl.='	<p><label for="el'.$this->element_num.'"> '.$key.': </label><input id="el'.$this->element_num.'" type="text" name="'.$key.'" value="'.$item['var'].'" />'.$comment.'</p>'.PHP_EOL;
			$this->element_num++;
		}

		$this->tpl.='</div>'.PHP_EOL.'	<p class="form_bottom"><input type="submit" value="Save" /></p>';
		$this->tpl.='</form></fieldset></div>'.PHP_EOL;
	}

}
