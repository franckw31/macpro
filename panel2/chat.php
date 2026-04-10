<?php
session_start();
include('include/config.php');
$conx = $con;

if (!isset($_SESSION['id'])) {
    header("Location: logout.php");
    exit();
}

$user_id = $_SESSION['id'];
$is_admin = false;

// Check if user is admin (ID 265 or droits = 2)
$res = mysqli_query($conx, "SELECT `droits` FROM `membres` WHERE `id-membre` = $user_id");
if ($row = mysqli_fetch_assoc($res)) {
    if ($user_id == 265 || $row['droits'] == '2') {
        $is_admin = true;
    }
}
$_SESSION['is_admin'] = $is_admin;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Chat Joueurs</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
    <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
    <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
    <link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    
    <!-- Modern Dashboard CSS -->
    <link rel="stylesheet" href="assets/css/modern-dashboard.css">
    
    <!-- Chat Specific CSS -->
    <link rel="stylesheet" href="../chat/style.css">
    
    <style>
        .chat-container {
            height: calc(100vh - 250px) !important;
            min-height: 600px;
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            width: 100%;
            background-color: #fff;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.06), 0 2px 5px 0 rgba(0,0,0,.2);
        }
        body {
            display: block !important;
            height: auto !important;
            background-color: #f7f7f8 !important;
        }
        .main-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #e5ddd5;
            height: 100%;
        }
        .sidebar {
            height: 100%;
        }
        #app {
            height: 100%;
        }
        .chat-header {
            background-color: #ededed;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            scroll-behavior: smooth;
        }
        .chat-input-area {
            padding: 10px 15px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
        }
        #message-input {
            border: none;
            padding: 10px;
            border-radius: 20px;
            margin: 0 10px;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .date-separator {
            text-align: center;
            margin: 20px 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .date-separator::before {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0,0,0,0.1);
            z-index: 1;
        }
        .date-label {
            background: #e1f3fb;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 12px;
            color: #555;
            position: relative;
            z-index: 2;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body>
    <div id="app">
        <?php
        $fiche = $_SESSION['id'];
        include('include/sidebar.php');
        ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <!-- Page Title -->
                    <section id="page-title" style="padding: 15px 0;">
                        <div class="row">
                            <div class="col-sm-12 text-center">
                                <h2 class="mainTitle" style="color:white; margin: 0;">Chat Joueurs</h2>
                                <span class="mainDescription" style="color: rgba(255,255,255,0.8);">Communiquez avec les autres joueurs en temps réel</span>
                            </div>
                        </div>
                    </section>

                    <div class="container-fluid container-fullw bg-white" style="padding: 10px;">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="chat-container">
                                    <div class="sidebar">
                                        <div class="sidebar-header">
                                            <h3>Francky Zap</h3>
                                            <span><?php echo $_SESSION['login']; ?></span>
                                        </div>
                                        <div class="sidebar-tabs">
                                            <div class="sidebar-tab active" onclick="selectGlobal()">Fil</div>
                                            <div class="sidebar-tab" onclick="showTab('groups')">Groupes</div>
                                            <div class="sidebar-tab" onclick="showTab('friends')">Amis</div>
                                            <div class="sidebar-tab" onclick="showTab('contacts')">Contacts</div>
                                        </div>
                                        <div class="contact-list" id="friends-list" style="display:none;">
                                            <!-- Friends will be loaded here -->
                                        </div>
                                        <div class="contact-list" id="contact-list" style="display:none;">
                                            <!-- Contacts will be loaded here -->
                                        </div>
                                        <div class="group-list" id="group-list" style="display:none;">
                                            <div class="create-group-btn" onclick="openCreateGroupModal()">+ Créer un Groupe</div>
                                            <div id="groups-container"></div>
                                        </div>
                                    </div>

                                    <div class="main-chat">
                                        <div class="chat-header" id="chat-header">
                                            <img src="" alt="" class="contact-photo" id="header-photo" style="display:none;">
                                            <h4 id="header-name" style="flex:1; margin:0;">Sélectionnez un contact</h4>
                                            <button id="manage-group-btn" style="display:none; background:#075e54; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;" onclick="openManageGroupModal()">Gérer le groupe</button>
                                        </div>
                                        
                                        <div class="chat-input-area" id="input-area" style="display:none; border-bottom: 1px solid #ddd;">
                                            <input type="file" id="image-input" style="display:none;" accept="image/*">
                                            <button id="attach-btn" onclick="$('#image-input').click()" style="font-size:20px; margin-right:10px; background:none; border:none; cursor:pointer;">📎</button>
                                            <button id="voice-btn" style="font-size:20px; margin-right:10px; background:none; border:none; cursor:pointer; color:#075e54;">🎤</button>
                                            <input type="text" id="message-input" placeholder="Tapez un message..." style="flex:1;">
                                            <button id="send-btn" style="background:none; border:none; font-size:24px; color:#075e54; cursor:pointer;">➤</button>
                                        </div>

                                        <div id="recording-status" style="display:none; padding:10px; background:#fff5f5; border-bottom:1px solid #ddd; align-items:center; justify-content:space-between;">
                                            <div style="display:flex; align-items:center;">
                                                <span style="color:red; margin-right:10px;">● Enregistrement...</span>
                                                <span id="recording-cardevent">00:00</span>
                                            </div>
                                            <div>
                                                <button onclick="cancelRecording()" style="color:red; background:none; border:none; cursor:pointer; margin-right:15px;">Annuler</button>
                                                <button onclick="stopRecording()" style="color:green; background:none; border:none; cursor:pointer; font-weight:bold;">Envoyer</button>
                                            </div>
                                        </div>

                                        <div id="image-preview" style="display:none; padding:10px; background:#f0f0f0; border-bottom:1px solid #ddd;">
                                            <span id="preview-filename"></span>
                                            <button onclick="clearImage()" style="color:red; margin-left:10px;">&times;</button>
                                        </div>

                                        <div class="chat-messages" id="chat-messages">
                                            <!-- Messages will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include('include/footer.php'); ?>
        <?php include('include/setting.php'); ?>
    </div>

    <!-- Modals -->
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Créer un nouveau groupe</h4>
                <span style="cursor:pointer" onclick="closeCreateGroupModal()">&times;</span>
            </div>
            <div style="margin-top:15px;">
                <input type="text" id="new-group-name" placeholder="Nom du groupe" style="width:100%; padding:8px; margin-bottom:10px;">
                <h5>Sélectionner les membres :</h5>
                <div class="member-select-list" id="member-select-list">
                    <!-- Members will be loaded here -->
                </div>
                <button onclick="submitCreateGroup()" style="width:100%; padding:10px; background:#075e54; color:white; border:none; border-radius:4px; cursor:pointer;">Créer</button>
            </div>
        </div>
    </div>

    <div id="manageGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="manage-group-title">Gérer le groupe</h4>
                <span style="cursor:pointer" onclick="closeManageGroupModal()">&times;</span>
            </div>
            <div style="margin-top:15px;">
                <div style="margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid #eee;">
                    <h5>Renommer le groupe :</h5>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="edit-group-name" style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <button onclick="submitRenameGroup()" style="background:#075e54; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer;">OK</button>
                    </div>
                </div>
                <div style="margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid #eee;">
                    <h5>Cloner le groupe (copier les membres) :</h5>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="clone-group-name" placeholder="Nom du nouveau groupe" style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <button onclick="submitCloneGroup()" style="background:#25d366; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer;">Cloner</button>
                    </div>
                </div>
                <h5>Membres actuels :</h5>
                <div id="current-members-list" style="max-height:150px; overflow-y:auto; margin-bottom:15px; border-bottom:1px solid #eee;">
                    <!-- Current members will be loaded here -->
                </div>
                <h5>Ajouter des membres :</h5>
                <div id="add-members-list" style="max-height:150px; overflow-y:auto;">
                    <!-- Non-members will be loaded here -->
                </div>
                <div style="margin-top:20px; padding-top:15px; border-top:2px solid #ff000022;">
                    <button onclick="confirmDeleteGroup()" style="width:100%; padding:10px; background:#dc3545; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Supprimer le groupe</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/modernizr/modernizr.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/switchery/switchery.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        jQuery(document).ready(function () {
            Main.init();
        });

        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        const userId = <?php echo $user_id; ?>;
        let currentContactId = null;
        let currentGroupId = null;
        let currentGroupCreator = null;
        let allContacts = [];
        let lastMessageId = 0;
        let lastSyncTime = '2000-01-01 00:00:00';
        let lastDisplayedDate = null;
        let totalUnread = 0;
        const notificationSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');

        // Voice Recording Variables
        let mediaRecorder;
        let audioChunks = [];
        let recordingInterval;
        let recordingSeconds = 0;

        function startRecording() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert("L'enregistrement audio n'est pas supporté par votre navigateur ou nécessite une connexion sécurisée (HTTPS).");
                return;
            }

            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    let options = {};
                    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                        options = { mimeType: 'audio/webm;codecs=opus' };
                    } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                        options = { mimeType: 'audio/mp4' };
                    }

                    mediaRecorder = new MediaRecorder(stream, options);
                    mediaRecorder.start();
                    audioChunks = [];

                    mediaRecorder.addEventListener("dataavailable", event => {
                        audioChunks.push(event.data);
                    });

                    $('#input-area').hide();
                    $('#recording-status').css('display', 'flex');
                    recordingSeconds = 0;
                    $('#recording-cardevent').text('00:00');
                    
                    recordingInterval = setInterval(() => {
                        recordingSeconds++;
                        let mins = Math.floor(recordingSeconds / 60).toString().padStart(2, '0');
                        let secs = (recordingSeconds % 60).toString().padStart(2, '0');
                        $('#recording-cardevent').text(`${mins}:${secs}`);
                    }, 1000);
                })
                .catch(err => {
                    alert("Erreur d'accès au micro : " + err);
                });
        }

        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                clearInterval(recordingInterval);
                
                mediaRecorder.addEventListener("stop", () => {
                    console.log("Recording stopped, chunks:", audioChunks.length);
                    if (audioChunks.length > 0) {
                        const mimeType = mediaRecorder.mimeType || 'audio/webm';
                        const audioBlob = new Blob(audioChunks, { type: mimeType });
                        console.log("Audio blob created, size:", audioBlob.size, "type:", mimeType);
                        sendVoiceMessage(audioBlob);
                    } else {
                        console.error("No audio data captured");
                        alert("L'enregistrement a échoué : aucune donnée capturée.");
                    }
                    
                    // Stop all tracks to release microphone
                    if (mediaRecorder.stream) {
                        mediaRecorder.stream.getTracks().forEach(track => track.stop());
                    }
                    
                    $('#recording-status').hide();
                    $('#input-area').show();
                }, { once: true });

                mediaRecorder.stop();
            }
        }

        function cancelRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                clearInterval(recordingInterval);
                
                if (mediaRecorder.stream) {
                    mediaRecorder.stream.getTracks().forEach(track => track.stop());
                }
                
                $('#recording-status').hide();
                $('#input-area').show();
            }
        }

        function sendVoiceMessage(blob) {
            if (!currentContactId && !currentGroupId) return;

            let formData = new FormData();
            let extension = blob.type.includes('mp4') ? 'mp4' : 'webm';
            formData.append('audio', blob, 'voice_message.' + extension);
            
            if (currentGroupId) formData.append('group_id', currentGroupId);
            else formData.append('receiver_id', currentContactId);

            $.ajax({
                url: '../chat/send_message.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        loadMessages();
                    } else {
                        alert('Erreur envoi vocal: ' + (res ? res.error : 'Inconnu'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", status, error, xhr.responseText);
                }
            });
        }

        function showTab(tab) {
            $('.sidebar-tab').removeClass('active');
            $('#contact-list, #group-list, #friends-list').hide();
            
            if (tab === 'groups') {
                $('.sidebar-tab:nth-child(2)').addClass('active');
                $('#group-list').show();
            } else if (tab === 'friends') {
                $('.sidebar-tab:nth-child(3)').addClass('active');
                $('#friends-list').show();
            } else if (tab === 'contacts') {
                $('.sidebar-tab:nth-child(4)').addClass('active');
                $('#contact-list').show();
            }
        }

        function selectGlobal() {
            currentContactId = null;
            currentGroupId = null;
            lastMessageId = 0;
            lastSyncTime = '2000-01-01 00:00:00';
            lastDisplayedDate = null;
            $('.sidebar-tab').removeClass('active');
            $('.sidebar-tab:nth-child(1)').addClass('active');
            $('.contact-item').removeClass('active');
            $('#chat-messages').html('');
            $('#header-name').text('Tous les messages');
            $('#header-photo').hide();
            $('#manage-group-btn').hide();
            $('#input-area').hide(); 
            $('#contact-list').hide();
            $('#friends-list').hide();
            $('#group-list').hide();
            loadMessages();
        }

        function replyToPrivate(id) {
            let contact = allContacts.find(c => c.id == id);
            if (contact) {
                selectContact(contact.id, contact.pseudo, contact.photo);
            }
        }

        function loadSidebar() {
            $.getJSON('../chat/get_contacts.php', function(data) {
                if (data.error) {
                    console.error("Sidebar error:", data.error);
                    return;
                }
                let friends = data.friends || [];
                let contacts = data.contacts || [];
                allContacts = friends.concat(contacts);
                let newTotalUnread = 0;
                
                // Load Friends
                let friendsHtml = '';
                friends.forEach(contact => {
                    let unread = parseInt(contact.unread_count);
                    newTotalUnread += unread;
                    let badge = unread > 0 ? `<span class="unread-badge">${unread}</span>` : '';
                    let activeClass = currentContactId == contact.id ? 'active' : '';
                    let escapedPseudo = (contact.pseudo || '').replace(/'/g, "\\'");
                    
                    friendsHtml += `
                        <div class="contact-item ${activeClass}" id="contact-${contact.id}" onclick="selectContact(${contact.id}, '${escapedPseudo}', '${contact.photo}')">
                            <img src="../images/faces/${contact.photo}" class="contact-photo">
                            <span>${contact.pseudo}</span>
                            ${badge}
                        </div>
                    `;
                });
                $('#friends-list').html(friendsHtml);

                // Load Contacts
                let contactHtml = '';
                contacts.forEach(contact => {
                    let unread = parseInt(contact.unread_count);
                    newTotalUnread += unread;
                    let badge = unread > 0 ? `<span class="unread-badge">${unread}</span>` : '';
                    let activeClass = currentContactId == contact.id ? 'active' : '';
                    let escapedPseudo = (contact.pseudo || '').replace(/'/g, "\\'");
                    
                    contactHtml += `
                        <div class="contact-item ${activeClass}" id="contact-${contact.id}" onclick="selectContact(${contact.id}, '${escapedPseudo}', '${contact.photo}')">
                            <img src="../images/faces/${contact.photo}" class="contact-photo">
                            <span>${contact.pseudo}</span>
                            ${badge}
                        </div>
                    `;
                });
                $('#contact-list').html(contactHtml);

                // Load Groups
                let groupHtml = '';
                (data.groups || []).forEach(group => {
                    let unread = parseInt(group.unread_count || 0);
                    newTotalUnread += unread;
                    let badge = unread > 0 ? `<span class="unread-badge">${unread}</span>` : '';
                    let activeClass = currentGroupId == group.id ? 'active' : '';
                    let escapedName = (group.name || '').replace(/'/g, "\\'");
                    groupHtml += `
                        <div class="contact-item ${activeClass}" id="group-${group.id}" onclick="selectGroup(${group.id}, '${escapedName}', ${group.created_by})">
                            <div class="contact-photo" style="background:#075e54; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold;">${(group.name || '?').charAt(0)}</div>
                            <span>${group.name}</span>
                            ${badge}
                        </div>
                    `;
                });
                $('#groups-container').html(groupHtml);

                if (newTotalUnread > totalUnread) {
                    notificationSound.play().catch(e => console.log("Audio play blocked"));
                }
                totalUnread = newTotalUnread;

                // Load Member Selection for Modal (Admins only)
                if (isAdmin) {
                    let memberSelectHtml = '';
                    allContacts.forEach(contact => {
                        memberSelectHtml += `
                            <div style="padding:5px;">
                                <input type="checkbox" class="group-member-checkbox" value="${contact.id}"> ${contact.pseudo}
                            </div>
                        `;
                    });
                    $('#member-select-list').html(memberSelectHtml);
                }
            });
        }

        function selectContact(id, name, photo) {
            let isFriend = $('#friends-list').find(`#contact-${id}`).length > 0;
            showTab(isFriend ? 'friends' : 'contacts');
            
            currentContactId = id;
            currentGroupId = null;
            lastMessageId = 0;
            lastSyncTime = '2000-01-01 00:00:00';
            lastDisplayedDate = null;
            $('#chat-messages').html('');
            $('#header-name').text(name);
            $('#header-photo').attr('src', '../images/faces/' + photo).show();
            $('#manage-group-btn').hide();
            $('#input-area').show();
            $('.contact-item').removeClass('active');
            $(`#contact-${id}`).addClass('active');
            loadMessages();
        }

        function selectGroup(id, name, creator = null) {
            showTab('groups');
            currentGroupId = id;
            currentGroupCreator = creator;
            currentContactId = null;
            lastMessageId = 0;
            lastSyncTime = '2000-01-01 00:00:00';
            lastDisplayedDate = null;
            $('#chat-messages').html('');
            $('#header-name').text(name);
            $('#header-photo').hide();
            // Show manage button if user is admin or group creator
            if (isAdmin || (currentGroupCreator && currentGroupCreator == userId)) {
                $('#manage-group-btn').show();
            } else {
                $('#manage-group-btn').hide();
            }
            $('#input-area').show();
            $('.contact-item').removeClass('active');
            $(`#group-${id}`).addClass('active');
            loadMessages();
        }

        function openManageGroupModal() {
            if (!currentGroupId) return;
            let currentName = $('#header-name').text();
            $('#manage-group-title').text('Gérer le groupe : ' + currentName);
            $('#edit-group-name').val(currentName);
            $('#clone-group-name').val(currentName + ' (Copie)');
            loadGroupDetails();
            $('#manageGroupModal').show();
        }

        function submitRenameGroup() {
            let newName = $('#edit-group-name').val();
            if (!newName) return;

            $.post('../chat/rename_group.php', {
                group_id: currentGroupId,
                new_name: newName
            }, function(response) {
                let res = JSON.parse(response);
                if (res.success) {
                    $('#header-name').text(newName);
                    loadSidebar();
                } else {
                    alert('Erreur: ' + res.error);
                }
            });
        }

        function submitCloneGroup() {
            let newName = $('#clone-group-name').val();
            if (!newName) {
                alert('Veuillez entrer un nom pour le nouveau groupe');
                return;
            }

            $.post('../chat/clone_group.php', {
                group_id: currentGroupId,
                new_name: newName
            }, function(response) {
                let res = JSON.parse(response);
                if (res.success) {
                    alert('Groupe cloné avec succès !');
                    $('#clone-group-name').val('');
                    closeManageGroupModal();
                    loadSidebar();
                    selectGroup(res.new_group_id, newName);
                } else {
                    alert('Erreur: ' + res.error);
                }
            });
        }

        function censorMessage(messageId) {
            if (confirm("Voulez-vous vraiment censurer ce message ?")) {
                $.post('../chat/censor_message.php', {
                    message_id: messageId
                }, function(response) {
                    let res = JSON.parse(response);
                    if (res.success) {
                        loadMessages();
                    } else {
                        alert('Erreur: ' + res.error);
                    }
                });
            }
        }

        function uncensorMessage(messageId) {
            if (confirm("Voulez-vous vraiment décensurer ce message ?")) {
                $.post('../chat/uncensor_message.php', {
                    message_id: messageId
                }, function(response) {
                    let res = JSON.parse(response);
                    if (res.success) {
                        loadMessages();
                    } else {
                        alert('Erreur: ' + res.error);
                    }
                });
            }
        }

        function closeManageGroupModal() {
            $('#manageGroupModal').hide();
        }

        function loadGroupDetails() {
            $.getJSON('../chat/get_group_details.php', { group_id: currentGroupId }, function(data) {
                let currentHtml = '';
                data.current_members.forEach(member => {
                    currentHtml += `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:5px;">
                            <span>${member.pseudo}</span>
                            ${member.id != <?php echo $_SESSION['id']; ?> ? `<button onclick="manageMember(${member.id}, 'remove')" style="color:red; border:none; background:none; cursor:pointer;">Supprimer</button>` : '<i>(Vous)</i>'}
                        </div>
                    `;
                });
                $('#current-members-list').html(currentHtml || 'Aucun membre');

                let addHtml = '';
                data.non_members.forEach(member => {
                    addHtml += `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:5px;">
                            <span>${member.pseudo}</span>
                            <button onclick="manageMember(${member.id}, 'add')" style="color:green; border:none; background:none; cursor:pointer;">Ajouter</button>
                        </div>
                    `;
                });
                $('#add-members-list').html(addHtml || 'Tous les joueurs sont déjà membres');
            });
        }

        function manageMember(memberId, action) {
            $.post('../chat/manage_group_members.php', {
                group_id: currentGroupId,
                member_id: memberId,
                action: action
            }, function(response) {
                loadGroupDetails();
            });
        }

        function confirmDeleteGroup() {
            if (confirm("Êtes-vous sûr de vouloir supprimer ce groupe ? Cette action supprimera également tous les messages du groupe et est irréversible.")) {
                $.post('../chat/delete_group.php', {
                    group_id: currentGroupId
                }, function(response) {
                    let res = JSON.parse(response);
                    if (res.success) {
                        closeManageGroupModal();
                        currentGroupId = null;
                        $('#header-name').text('Sélectionnez un contact');
                        $('#manage-group-btn').hide();
                        $('#input-area').hide();
                        $('#chat-messages').html('');
                        loadSidebar();
                    } else {
                        alert('Erreur: ' + res.error);
                    }
                });
            }
        }

        function loadMessages() {
            let params = {};
            if (currentGroupId) params.group_id = currentGroupId;
            else if (currentContactId) params.contact_id = currentContactId;
            
            params.last_id = lastMessageId;
            params.last_sync = lastSyncTime;
            
            $.getJSON('../chat/get_messages.php', params, function(data) {
                if (data.error) {
                    console.error("Messages error:", data.error);
                    return;
                }
                if (Array.isArray(data) && data.length > 0) {
                    let html = '';
                    let hasNewReceived = false;
                    let maxUpdate = lastSyncTime;

                    // Sort data by ID ascending to process chronologically for date logic
                    data.sort((a, b) => a.id - b.id);

                    data.forEach(msg => {
                        if (msg.updated_at && msg.updated_at > maxUpdate) maxUpdate = msg.updated_at;
                        
                        let existingMsg = $(`#msg-${msg.id}`);
                        
                        // Date separation logic
                        let dateParts = msg.timestamp.split(' ')[0].split('/');
                        let formattedDate = msg.timestamp.split(' ')[0];
                        
                        if (dateParts.length === 3) {
                            const months = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
                            formattedDate = `${parseInt(dateParts[0])} ${months[parseInt(dateParts[1]) - 1]}`;
                            if (new Date().getFullYear() != dateParts[2]) {
                                formattedDate += ` ${dateParts[2]}`;
                            }
                        }

                        let dateHtml = '';
                        if (lastDisplayedDate !== formattedDate && !existingMsg.length) {
                            dateHtml = `<div class="date-separator"><span class="date-label">${formattedDate}</span></div>`;
                            lastDisplayedDate = formattedDate;
                        }

                        let isCensored = msg.is_censored == 1;
                        let bodyStyle = '';
                        if (isCensored) {
                            if (isAdmin) {
                                bodyStyle = 'style="color: red; font-weight: bold;"';
                            } else {
                                bodyStyle = 'style="font-style: italic;"';
                            }
                        }

                        if (existingMsg.length > 0) {
                            existingMsg.find('.message-body').html(msg.message).attr('style', isCensored ? (isAdmin ? 'color: red; font-weight: bold;' : 'font-style: italic;') : '');
                            existingMsg.find('.message-image, .audio-container, .censorship-label').remove();
                            
                            if (msg.image) {
                                let imgStyle = isCensored ? 'border: 3px solid red; opacity: 0.7;' : '';
                                let imgLabel = isCensored ? '<div class="censorship-label" style="color:red; font-size:10px; font-weight:bold;">IMAGE CENSURÉE</div>' : '';
                                existingMsg.find('.message-body').after(`${imgLabel}<img src="../chat/uploads/${msg.image}" class="message-image" style="${imgStyle}" onclick="window.open(this.src)">`);
                            }
                            if (msg.audio) {
                                let audioStyle = isCensored ? 'border: 2px solid red; border-radius: 30px;' : '';
                                let audioLabel = isCensored ? '<div class="censorship-label" style="color:red; font-size:10px; font-weight:bold;">AUDIO CENSURÉ</div>' : '';
                                let target = existingMsg.find('.message-image').length > 0 ? existingMsg.find('.message-image') : existingMsg.find('.message-body');
                                target.after(`${audioLabel}<div class="audio-container" style="${audioStyle}"><audio controls src="../chat/uploads/${msg.audio}" style="max-width:100%; margin-top:5px;"></audio></div>`);
                            }

                            if (isAdmin) {
                                let actionBtn = isCensored 
                                    ? `<span style="cursor:pointer; color:#28a745; margin-left:10px; font-size:10px;" onclick="uncensorMessage(${msg.id})">Décensurer</span>`
                                    : `<span style="cursor:pointer; color:#dc3545; margin-left:10px; font-size:10px;" onclick="censorMessage(${msg.id})">Censurer</span>`;
                                let timeDiv = existingMsg.find('.message-time');
                                timeDiv.find('span').remove();
                                timeDiv.append(actionBtn);
                            }
                        } else {
                            let type = msg.sender_id == <?php echo $_SESSION['id']; ?> ? 'sent' : 'received';
                            if (type === 'received') hasNewReceived = true;

                            let context = '';
                            if (!currentContactId && !currentGroupId) {
                                if (msg.group_id) {
                                    let gName = (msg.group_name || 'Groupe inconnu').replace(/'/g, "\\'");
                                    let replyPrivately = type === 'received' ? `<span style="cursor:pointer; color:#075e54; text-decoration:underline; margin-left:10px; font-size:10px;" onclick="replyToPrivate(${msg.sender_id})">Répondre en privé</span>` : '';
                                    context = `<div class="sender-name">[Groupe: ${msg.group_name || 'Inconnu'}] ${msg.sender_name} 
                                        <span style="cursor:pointer; color:#075e54; text-decoration:underline; margin-left:10px; font-size:10px;" onclick="selectGroup(${msg.group_id}, '${gName}')">Répondre au groupe</span>
                                        ${replyPrivately}
                                    </div>`;
                                } else {
                                    let otherId = msg.sender_id == <?php echo $_SESSION['id']; ?> ? msg.receiver_id : msg.sender_id;
                                    context = `<div class="sender-name">[Privé] ${msg.sender_name} <span style="cursor:pointer; color:#075e54; text-decoration:underline; margin-left:10px; font-size:10px;" onclick="replyToPrivate(${otherId})">Répondre</span></div>`;
                                }
                            } else if (currentGroupId && type === 'received') {
                                context = `<div class="sender-name">${msg.sender_name} <span style="cursor:pointer; color:#075e54; text-decoration:underline; margin-left:10px; font-size:10px;" onclick="replyToPrivate(${msg.sender_id})">Répondre en privé</span></div>`;
                            }

                            let actionBtn = '';
                            if (isAdmin) {
                                actionBtn = isCensored 
                                    ? `<span style="cursor:pointer; color:#28a745; margin-left:10px; font-size:10px;" onclick="uncensorMessage(${msg.id})">Décensurer</span>`
                                    : `<span style="cursor:pointer; color:#dc3545; margin-left:10px; font-size:10px;" onclick="censorMessage(${msg.id})">Censurer</span>`;
                            }

                            let imgHtml = '';
                            if (msg.image) {
                                let imgStyle = isCensored ? 'border: 3px solid red; opacity: 0.7;' : '';
                                let imgLabel = isCensored ? '<div class="censorship-label" style="color:red; font-size:10px; font-weight:bold;">IMAGE CENSURÉE</div>' : '';
                                imgHtml = `${imgLabel}<img src="../chat/uploads/${msg.image}" class="message-image" style="${imgStyle}" onclick="window.open(this.src)">`;
                            }

                            let audioHtml = '';
                            if (msg.audio) {
                                let audioStyle = isCensored ? 'border: 2px solid red; border-radius: 30px;' : '';
                                let audioLabel = isCensored ? '<div class="censorship-label" style="color:red; font-size:10px; font-weight:bold;">AUDIO CENSURÉ</div>' : '';
                                audioHtml = `${audioLabel}<div class="audio-container" style="${audioStyle}"><audio controls src="../chat/uploads/${msg.audio}" style="max-width:100%; margin-top:5px;"></audio></div>`;
                            }

                            let msgHtml = `
                                <div class="message-container ${type}" id="msg-${msg.id}">
                                    <img src="../images/faces/${msg.sender_photo}" class="message-sender-photo">
                                    <div class="message ${type}" data-id="${msg.id}">
                                        ${context}
                                        <div class="message-body" ${bodyStyle}>${msg.message}</div>
                                        ${imgHtml}
                                        ${audioHtml}
                                        <div class="message-time">${msg.timestamp} ${actionBtn}</div>
                                    </div>
                                </div>
                            `;
                            
                            // Prepend message and date separator to the batch HTML
                            html = msgHtml + dateHtml + html;
                            lastMessageId = Math.max(lastMessageId, msg.id);
                        }
                    });

                    if (html !== '') {
                        $('#chat-messages').prepend(html);
                    }
                    
                    lastSyncTime = maxUpdate;

                    if (hasNewReceived && lastMessageId !== 0) {
                        notificationSound.play().catch(e => console.log("Audio play blocked"));
                    }
                }
            });
        }

        function openCreateGroupModal() {
            $('#createGroupModal').show();
        }

        function closeCreateGroupModal() {
            $('#createGroupModal').hide();
        }

        function submitCreateGroup() {
            let name = $('#new-group-name').val();
            let members = [];
            $('.group-member-checkbox:checked').each(function() {
                members.push($(this).val());
            });

            if (!name) {
                alert('Veuillez entrer un nom de groupe');
                return;
            }

            $.post('../chat/create_group.php', {
                group_name: name,
                members: members
            }, function(response) {
                let res = JSON.parse(response);
                if (res.success) {
                    closeCreateGroupModal();
                    loadSidebar();
                    selectGroup(res.group_id, name);
                } else {
                    alert('Erreur: ' + res.error);
                }
            });
        }

        function clearImage() {
            $('#image-input').val('');
            $('#image-preview').hide();
            $('#preview-filename').text('');
        }

        $('#image-input').change(function() {
            if (this.files && this.files[0]) {
                $('#preview-filename').text('Image sélectionnée : ' + this.files[0].name);
                $('#image-preview').show();
            }
        });

        $(document).ready(function() {
            loadSidebar();
            
            // Gérer l'ouverture directe d'un groupe ou d'un contact via URL
            const urlParams = new URLSearchParams(window.location.search);
            const groupId = urlParams.get('group_id');
            const contactId = urlParams.get('contact_id');

            if (groupId) {
                // On attend un peu que le sidebar se charge pour avoir les éléments DOM
                setTimeout(() => {
                    let groupItem = $(`#group-${groupId}`);
                    if (groupItem.length) {
                        groupItem.click();
                    } else {
                        // Si pas encore dans le DOM, on essaie de récupérer le nom via AJAX
                        $.getJSON('../chat/get_group_details.php', { group_id: groupId }, function(res) {
                            if (res && res.success) {
                                selectGroup(groupId, res.group.name);
                            } else {
                                selectGlobal();
                            }
                        });
                    }
                }, 800);
            } else if (contactId) {
                setTimeout(() => {
                    let contactItem = $(`#contact-${contactId}`);
                    if (contactItem.length) {
                        contactItem.click();
                    } else {
                        selectGlobal();
                    }
                }, 800);
            } else {
                selectGlobal();
            }

            setInterval(loadMessages, 3000);
            setInterval(loadSidebar, 5000);

            $('#voice-btn').on('click', function() {
                startRecording();
            });

            $('#send-btn').on('click', function() {
                let message = $('#message-input').val().trim();
                let imageFile = $('#image-input')[0].files[0];
                
                if (!message && !imageFile) return;
                if (!currentContactId && !currentGroupId) return;

                let formData = new FormData();
                formData.append('message', message);
                if (imageFile) formData.append('image', imageFile);
                
                if (currentGroupId) formData.append('group_id', currentGroupId);
                else formData.append('receiver_id', currentContactId);

                $.ajax({
                    url: '../chat/send_message.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res && res.success) {
                            $('#message-input').val('');
                            clearImage();
                            loadMessages();
                        } else {
                            alert('Erreur: ' + (res ? res.error : 'Réponse inconnue'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", status, error, xhr.responseText);
                    }
                });
            });

            $('#message-input').on('keypress', function(e) {
                if (e.which == 13) {
                    $('#send-btn').trigger('click');
                }
            });
        });
    </script>
</body>
</html>

