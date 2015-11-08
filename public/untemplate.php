<?php
/**
 * Created by PhpStorm.
 * User: MOVe
 * Date: 18.02.14
 * Time: 22:01
 */
/*
	Предлагаю в шаблонах, которые будем обрабатывать, вписывать после строк что-то типа такого:
	max_execution_time = 30 ; {tpl}
	Это на выполнение не влияет, если что, и я автоматически обработать могу.
	Такое: display_errors = On
	display_errors = On ; {tpl:yes-no-list}

	Это говорит о том, что это нужно вывести в виде переключателя YES | NO
	date.timezone =  ; {tpl:timezone-list}

	Тут мы выдаём список с таймзонами. Короче, элемент управления, заточенный на выбор таймзон.
	И так далее
	Если делаем так, то по проходу по шаблону я могу автоматически менять эти поля под шаблон.
	Плюс сразу формировать информацию для построения форм с элементами управления.
	Ну, и стандартные:

	{tpl:text}
	{tpl:input}
	{tpl:password}

	И так далее
	Ты пройдясь по файлику сам определишь вид форм, которые сформируются по нему.
	Моя задача сделать все возможные списки, поля ввода и тому подобное.
	С твоими подсказками
	По-умолчанию это будет текстовое поле. То есть: {tpl} = {tpl:input}
	Параметр name у поля будет формироваться автоматически по названию переменной, например:
	<input name="inp_max_execution_time" />
	<select name="lst_date_timezone" />
	По-умолчанию я ставлю значение, которое написано в шаблоне.
 */

$files=glob('conf/*.tpl');
//echo '<pre>'; print_r($files); exit;
$tpl_cnt_start=
"<?php
include('../make.php');

class Tpl extends Make
{
	public \$form_elements=array();
	public \$file_name='';

	//private \$tpl;

	function __construct()
	{
";

$tpl_cnt_main_start=
"		\$this->initVariables();
	}

	function getConfig()
	{
		\$tpl = <<<EOT
";

$tpl_cnt_main_end=
"
EOT;
		return \$tpl;
";

$tpl_cnt_end=
"
	}
}

\$tpl=new Tpl();
\$tpl->render();
";

if(!empty($files))
{
	foreach($files as $key=>$file)
	{
		$file_php=$file.'.php';
		$tpl_cnt=$tpl_cnt_start;
		$tpl_cnt.=PHP_EOL."		\$this->file_name='../".$file."';".PHP_EOL;
		if(filemtime($file)>filemtime($file_php))
		{
			$cnt=file_get_contents($file);
			$cnt=str_replace('$','\$',$cnt);

			//$pat='#^(.*);\{tpl:?([^\}]*)\}[\s]*$#m';
			# tpl{var="max_execution_time";delim="=";type="text";val="30"}
			$pat='#tpl{var="([^";]+)";(delim="([^";]+)")?;?(type="?([^";]+)"?)?;?val="?([^"\}]+)"?}#m';
			preg_match_all($pat,$cnt,$res,PREG_SET_ORDER);
			//echo '<pre>';var_dump($res);exit;

			$variables=array();
			if(!empty($res))foreach($res as $key=>$item)
			{
				$tpl_source=$item[0];
				$tpl_var_name=trim($item[1]);
				$tpl_delimeter=$item[3];
				$tpl_type=trim($item[5]);
				$tpl_value=trim($item[6]);

				if(empty($tpl_type)) $tpl_type='input';
				if(!isset($item[3])) $tpl_delimeter=' = ';
				if($tpl_delimeter=='=') $tpl_delimeter=' '.$tpl_delimeter.' ';

				$tpl_cnt.="		\$this->form_elements['".$tpl_var_name."']=array('default'=>'".$tpl_value."','type'=>'".$tpl_type."');".PHP_EOL;

				$cnt_var=$tpl_var_name.$tpl_delimeter."{\$this->form_elements['".$tpl_var_name."']['var']}";
				$cnt=str_replace($tpl_source,$cnt_var,$cnt);
			}

		#	Add final config
			$tpl_cnt.=$tpl_cnt_main_start;
			$tpl_cnt.=$cnt;
			$tpl_cnt.=$tpl_cnt_main_end;
		#	End final config

			$tpl_cnt.=$tpl_cnt_end;

			file_put_contents($file_php,$tpl_cnt);

			highlight_file($file_php);
		}else{
			echo '<pre>No changes in file: ',$file,PHP_EOL,'</pre>';
		}
	}
}

/*
    [0] => Array
        (
            [0] => tpl{var="max_execution_time";delim="=";type="text";val="30"}
            [1] => max_execution_time
            [2] => delim="="
            [3] => =
            [4] => type="text"
            [5] => text
            [6] => 30
        )

    [1] => Array
        (
            [0] => tpl{var="max_input_time";delim="=";type="text";val="60"}
            [1] => max_input_time
            [2] => delim="="
            [3] => =
            [4] => type="text"
            [5] => text
            [6] => 60
        )
 */