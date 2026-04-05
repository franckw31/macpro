<?php
session_start();
$_SESSION['Email_Session']=="";
session_unset();
session_destroy();
?>
<script language="javascript">
document.location="../../index.php";
</script>