<?php
session_start();     
include_once('include/config.php');                                                                                                                                      
$fin0="";
$fin1="";
$nom1="";
$res=mysqli_query($con,"SELECT * FROM blindes WHERE id='1'");
while($row=mysqli_fetch_array($res))
{
    $fin1=$row["fin"];
    $nom1=$row["nom"];
};

$_SESSION["fin1"]=$fin1;
$_SESSION["nom1"]=$nom1;
$_SESSION["stop1"]='0';
$_SESSION["stop2"]='0';
?>
<div id="response">

<form>
  <input type="hidden" id="titre" value="<?php echo $nom1; ?>">
</form>
<!-- <div id="response"></div> -->


<div class='square-box' opacity:0.99>
        <div class='square-content' id="response" style='font-size:30px ; color:grey'></div>
    </div>

<!-- <div id="response2"></div> -->
<?php
 $fini1=$_SESSION['stop1'];
 $fini2=$_SESSION['stop2']; 
 
if ($_SESSION["stop1"] == '0') { ?>
    <div id="response"></div>
    
    <script type="text/javascript">
        function stopTimeout1() {clearInterval(cleartimer1); }
        let cleartimer1 = setInterval(function()
        {
            var xmlhttp=new XMLHttpRequest();   
                     
            xmlhttp.open("GET","response.php",false);
            xmlhttp.send(null);
            
            if (xmlhttp.responseText == 0) {clearInterval(cleartimer1)} else {document.getElementById("response").innerHTML=document.getElementById("titre").value+" : "+xmlhttp.responseText;}
        },1000);
    </script>
    <?php }
else
{
    ?>
    <!-- <script type="text/javascript"> window.location.href="http://poker31.org/toto.php" </script> -->
    <?php ; echo "stop1 car stop = ".$_SESSION['stop1'];
}

?> 