<style>
    body{
        background-image: url('/assets/background.jpg') !important;
    }
    .square-box {
        position: absolute;
        width: 90%;
        height: 50%;
        overflow: hidden;
        background: #4679BD;

        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        margin: auto;
        border-radius: 200px;
        border: 5px solid black;


    }

    .square-box:before {
        content: "";
        display: block;
        padding-top: 100%;
    }

    .square-content {
        position: absolute;
        top: 45%;
        left: 37%;
        color: white;
        width: 100%%;
        height: 100%%;
        font-size: 4vw;

    }

    .square-content div {
        display: table;
        width: 100%;
        height: 100%;
    }

    .square-content span {
        display: table-cell;
        text-align: center;
        vertical-align: middle;
        color: white
    }

.players {
    position: relative;
    width: 100%;
    height: 100%;
    z-index: 100;
    
}

.players .player {
    position: absolute;
}
.players .player.player-1 {
    top: 17%;
  
    left:50%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}

.players .player.player-2 {
    top: 22%;
    
    left:80%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}

.players .player.player-3 {
    top: 45%;
    left: 95%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}

.players .player.player-4 {
    top: 70%;
    left: 80%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}

.players .player.player-5 {
    top: 75%;
    left: 50%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}
.players .player.player-6 {
    top: 70%;
    left: 20%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}

.players .player.player-7 {
    top: 45%;
    left: 5%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}

.players .player.player-8 {
    top: 22%;
    left: 20%;
    -webkit-transform: translatex(-50%) translatey(-50%);
    transform: translatex(-50%) translatey(-50%);
}
.players .player .avatar {
    width: 10vh;
    height: 10vh;
    background-color: lightcoral;
    border-radius: 100%;
    position: relative;
    top: 20px;
    z-index: 1;
}
#main{
    position: absolute;
        width: 85%;
        height: 50%;
        overflow: none;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        margin: auto;
      

}

.p1{
  
  
  
  display: flex;
  align-items: center; 
  justify-content: center;
  text-align: center; 
  color: #FFF;
  font-weight: bold;
  font-size: 3vw;
 

}
.p2{
  
  display: flex;
  align-items: center; 
  justify-content: center;
  text-align: center; 
  color: #fff;
  font-weight: bold;
  font-size: 3vw;
  opacity: 0.9;
 
}
.p3{
   
  display: flex;
  align-items: center; 
  justify-content: center;
  text-align: center; 
  color: #fff;
  font-weight: bold;
  font-size: 3vw;
  opacity: 0.95;
 
}
.p4{
   
   display: flex;
   align-items: center; 
   justify-content: center;
   text-align: center; 
   color: #fff;
   font-weight: bold;
   font-size: 3vw;
   opacity: 0.9;
  
 }
 .p5{
   
   display: flex;
   align-items: center; 
   justify-content: center;
   text-align: center; 
   color: #fff;
   font-weight: bold;
   font-size: 3vw;
   opacity: 0.8;
  
 }
 .p6{
   
   display: flex;
   align-items: center; 
   justify-content: center;
   text-align: center; 
   color: #fff;
   font-weight: bold;
   font-size: 2.5vw;
   opacity: 0.90;
  
 }
 .p7{
   
   display: flex;
   align-items: center; 
   justify-content: center;
   text-align: center; 
   color: #fff;
   font-weight: bold;
   font-size: 2.5vw;
   opacity: 0.95;
  
 }
 .p8{
   
   display: flex;
   align-items: center; 
   justify-content: center;
   text-align: center; 
   color: #fff;
   font-weight: bold;
   font-size: 2.5vw;
   opacity: 0.9;
  
 }
</style>

<div id="main" >
    <div class="players">
        <div class="player player-1 playing" id="player1" >
            <div class="avatar p1" style="background: blue;">
                <?php echo "Btn"; ?>
            </div>
            

        </div>

        <div class="player player-2 playing"  id="player2">
            <div class="avatar p2" style="background: red;">
              <?php echo "SB"; ?>
            </div>
        </div>
        <div class="player player-3 playing"  id="player3">
            <div class="avatar p3" style="background: yello;">
                <?php echo "BB"; ?>
            </div>
        </div>
        <div class="player player-4 playing"  id="player4">
            <div class="avatar p4" style="background: green;">
                <?php echo "UTG"; ?>
            </div>
        </div>
        <div class="player player-5 playing"  id="player5">
            <div class="avatar p5" style="background: black;">
                <?php echo "UTG+1"; ?>
            </div>
        </div>
        <div class="player player-6 playing"  id="player6">
            <div class="avatar p6" style="background: brown;">
                <?php echo "LOWJACK"; ?>
            </div>
        </div>
        <div class="player player-7 playing"  id="player7">
            <div class="avatar p7" style="background: pink;">
                <?php echo "HIGHJACK"; ?>
            </div>
        </div>
        <div class="player player-8 playing"  id="player8">
            <div class="avatar p8" style="background: purple;">
                <?php echo "CUTOFF"; ?>
            </div>
        </div>

    </div>
<div class='square-box'>
    
    <div class='square-content'> <span>TABLE N°1</span></div>
        
       
    </div>
</div>   
