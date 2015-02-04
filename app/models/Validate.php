<?php

class Validate extends Audit 
{

	public function isString($data, $length = null) 
	{
		$check = preg_match("#^[a-zA-Z ]{1,}$#", $data);
		$length = (!empty($length)) ? (strlen($data) <= $length) : true;
		return ($check == 1) && ($length);
	}

	public function isNumber($data, $length = null)
	{
		$check = preg_match("#^[0-9]{1,}$#", $data);
		$length = (!empty($length)) ? (strlen($data) <= $length) : true;
		return ($check == 1) && ($length);
	}


	public function isPassword($data, $entropy = null, $length = null)
	{

		$check = preg_match("#^[^ ;'\"]{8,25}#", $data);
		$length = (!empty($length)) ? (strlen($data) <= $length) : true;
		return ($check == 1) && ($length) && ($this->entropy($data) > $entropy);
	}


}

 ?>