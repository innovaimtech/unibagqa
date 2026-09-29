<?php
class Conexion
{

    private $conection = null;

    public function getConexion()
    {

        $this->conection = new PDO("mysql:host=localhost;dbname=unibag_unibag","unibag_unibag","ccsadfsdM1Ffd12");
        $this->conection->exec("set names utf-8");
        return $this->conection;
    }

    public function closeDataBase()
    {

        if($this->conection!=null)
        {

            $this->conection = null;
        }
    }


}