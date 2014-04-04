<?php

class dbAbstraction
{

private $driver;

public function __construct($drivernaam)
{
	if (strtolower($drivernaam) == 'mysql')
	{
		$this->driver = new driverMySQL();
	}
	elseif (strtolower($drivernaam) == 'mysqli')
	{
		$this->driver = new driverMySQLi();
	}
}

public function connect($login, $pass, $naam, $host = 'localhost')
{
	$this->driver->connect($login, $pass, $naam, $host);
}

public function query($sql)
{
	$this->driver->query($sql);
}

public function fetch_assoc()
{
	return $this->driver->fetch_assoc();
}

public function num_rows()
{
	return $this->driver->num_rows();
}

public function escape($value)
{
	return $this->driver->quote_smart($value);
}

public function close()
{
	$this->driver->close();
}

}



abstract class driver
{

public abstract function connect($login, $pass, $naam, $host);
public abstract function query($sql);
public abstract function fetch_assoc();
public abstract function num_rows();
public abstract function close();
public abstract function quote_smart($value);
protected $error = false;

public function error($fout, $line, $file)
{
	printf('<div style="font: 12px Courier New, monospace;">
		<span style="color: #ff0000; font-weight: bold;">! Fout :</span> %s<br>
		op regel <span style="font-weight: bold;">%s</span>
		in bestand <span style="font-weight: bold;">%s</span></div>', $fout, $line, $file);
	$this->error = true;
}

}



class driverMySQL extends driver
{

private $connectie;
private $recentse_result;

public function connect($login, $pass, $naam, $host)
{
	$this->connectie = @mysql_connect($host, $login, $pass);
	$select = @mysql_select_db($naam, $this->connectie);
	if ($this->connectie === false || !$select)
		$this->error("Kon geen verbinding maken met de database.", __LINE__, __FILE__);
}

public function query($sql)
{
	if (!$this->error)
	{
		$this->recentse_result = mysql_query($sql, $this->connectie);
		if ($this->recentse_result === false)
			$this->error(mysql_error($this->connectie)."<br>SQL: ".$sql, __LINE__, __FILE__);
		
		return $this->recentse_result;
	}
}

public function fetch_assoc()
{
	if (!$this->error) return mysql_fetch_assoc($this->recentse_result);
}

public function num_rows()
{
    if (!$this->error) return mysql_num_rows($this->recentse_result);
    else return 0;
}

public function close()
{
	if (!$this->error) mysql_close($this->connectie);
}

public function quote_smart($value)
{
    if (get_magic_quotes_gpc())
    {
       $value = stripslashes($value);
    }
    if (!is_numeric($value))
    {
       $value = "'".mysql_real_escape_string($value)."'";
    }
    return $value;
}

}



class driverMySQLi extends driver
{

private $connectie;
private $recentse_result;

public function connect($login, $pass, $naam, $host)
{
	$this->connectie = @new mysqli($host, $login, $pass, $naam);
	if ($this->connectie === false)
		$this->error($this->connectie->error, __LINE__, __FILE__);
}

public function query($sql)
{
	if (!$this->error)
	{
		$this->recentse_result = $this->connectie->query($sql);
		if ($this->recentse_result === false)
			$this->error($this->connectie->error."<br>SQL: ".$sql, __LINE__, __FILE__);
		
		return $this->recentse_result;
	}
}

public function fetch_assoc()
{
	if (!$this->error) return $this->recentse_result->fetch_assoc();
}

public function num_rows()
{
    if (!$this->error) return $this->recentse_result->num_rows;
    else return 0;
}

public function close()
{
	if (!$this->error) $this->connectie->close();
}

public function quote_smart($value)
{
    if (get_magic_quotes_gpc())
    {
       $value = stripslashes($value);
    }
    if (!is_numeric($value))
    {
       $value = "'".$this->connectie->real_escape_string($value)."'";
    }
    return $value;
}

}

?>
