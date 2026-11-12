<?php

if (!empty($_POST["btn_entrar"])){
    if(empty($_POST["kronos"])|| empty($_POST['contra'])){
        echo "Los campos estan vacios";
    }else{
        $kronos = $_POST["kronos"];
        $contra = $_POST["contra"];

        $sql = $connection->query("select * from empleado where id_kronos='$kronos' and contra='$contra'");
        if ($datos=$sql->fetch_object()) {
            header("location:dashboard.html");
        } else {
            echo "Error";
        }
        

    }

}


?>