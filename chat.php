<?php
session_start();
require 'db.php'; // your database connection

// 1) AUTH CHECK - Make sure we're properly logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Clear any potentially corrupted session
    session_destroy();
    header('Location: index.php');
    exit;
}
$meId  = $_SESSION['user_id'];
$meNum = $_SESSION['unique_number']; // e.g. "+786-1001"

// 2) LOAD CONTACTS
$stm = $conn->prepare("SELECT unique_number FROM users WHERE id != ?");
$stm->bind_param("i", $meId);
$stm->execute();
$contacts = $stm->get_result();

// 3) WHO TO CHAT WITH?
$toNum = $_GET['to'] ?? '';
$toId  = null;
if ($toNum) {
    $stm = $conn->prepare("SELECT id FROM users WHERE unique_number = ?");
    $stm->bind_param("s", $toNum);
    $stm->execute();
    $res = $stm->get_result();
    if ($row = $res->fetch_assoc()) {
        $toId = $row['id'];
    }
}

// 4) SEND MESSAGE
if ($_SERVER['REQUEST_METHOD']==='POST' && $toId && !empty($_POST['message'])) {
    $text = trim($_POST['message']);
    $ins = $conn->prepare("
      INSERT INTO messages (sender_id, receiver_id, message)
      VALUES (?, ?, ?)
    ");
    $ins->bind_param("iis", $meId, $toId, $text);
    $ins->execute();
    
    // Redirect to prevent form resubmission
    header("Location: chat.php?to=" . urlencode($toNum));
    exit;
}

// 5) FETCH CHAT HISTORY
$history = [];
if ($toId) {
    $q = "
      SELECT u.unique_number AS sender, m.message, m.`timestamp` AS ts
      FROM messages m
      JOIN users u ON m.sender_id = u.id
      WHERE (m.sender_id = ? AND m.receiver_id = ?)
         OR (m.sender_id = ? AND m.receiver_id = ?)
      ORDER BY m.`timestamp` ASC
    ";
    $stm = $conn->prepare($q);
    $stm->bind_param("iiii", $meId, $toId, $toId, $meId);
    $stm->execute();
    $history = $stm->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($toNum ?: '...') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            margin: 0;
            display: flex;
            height: 100vh;
            background-color: #e5ddd5;
            overflow: hidden;
        }

        /* Left Sidebar */
        .sidebar {
            width: 320px;
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e0e0e0;
        }
        
        .sidebar-header {
            background-color: #075E54;
            color: #fff;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .logout-btn {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }
        
        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        .user-profile {
            background-color: #f0f2f5;
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
        }
        
        .user-info {
            flex-grow: 1;
            color: #075E54;
            font-weight: bold;
        }
        
        .contacts-title {
            padding: 15px;
            background-color: #f0f2f5;
            font-weight: bold;
            color: #075E54;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .contacts-list {
            flex-grow: 1;
            overflow-y: auto;
        }
        
        .contact {
            padding: 12px 15px;
            display: block;
            color: #075E54;
            text-decoration: none;
            border-bottom: 1px solid #f0f2f5;
            transition: background-color 0.2s;
        }
        
        .contact:hover {
            background-color: #f5f6f6;
        }
        
        .contact.active {
            background-color: #e9ebeb;
            font-weight: bold;
        }

        /* Main Chat Area */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #e5ddd5;
            background-image: url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23b1b1b1' fill-opacity='0.1'%3E%3Cpath d='M50 50c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10c0 5.523-4.477 10-10 10s-10-4.477-10-10 4.477-10 10-10zM10 10c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10c0 5.523-4.477 10-10 10S0 25.523 0 20s4.477-10 10-10zm10 8c4.418 0 8-3.582 8-8s-3.582-8-8-8-8 3.582-8 8 3.582 8 8 8zm40 40c4.418 0 8-3.582 8-8s-3.582-8-8-8-8 3.582-8 8 3.582 8 8 8z' /%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .header {
            background-color: #075E54;
            color: #fff;
            padding: 15px;
            font-weight: bold;
            display: flex;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .chat-icon {
            margin-right: 10px;
            font-size: 24px;
        }
        
        .msgs {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .msg {
            max-width: 65%;
            margin: 5px 0;
            padding: 10px 15px;
            border-radius: 7.5px;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
        }
        
        .sent {
            background-color: #dcf8c6;
            align-self: flex-end;
            border-top-right-radius: 0;
        }
        
        .recv {
            background-color: #fff;
            align-self: flex-start;
            border-top-left-radius: 0;
        }
        
        .ts {
            display: block;
            font-size: 11px;
            color: #555;
            margin-top: 5px;
            text-align: right;
        }
        
        .input {
            display: flex;
            padding: 10px;
            background-color: #f0f0f0;
            border-top: 1px solid #ddd;
        }
        
        .input input {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 20px;
            font-size: 15px;
            outline: none;
        }
        
        .input button {
            background-color: #128C7E;
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            cursor: pointer;
            font-size: 18px;
            transition: background-color 0.2s;
        }
        
        .input button:hover {
            background-color: #075E54;
        }
        
        .no-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #555;
            text-align: center;
            padding: 20px;
            font-size: 18px;
        }
        
        .no-chat-icon {
            font-size: 80px;
            color: #128C7E;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">WhatsApp Clone</div>
            <a href="index.php?logout=1" class="logout-btn">Logout</a>
        </div>
        
        <div class="user-profile">
            <div class="user-info">
                You: <?= htmlspecialchars($meNum) ?>
            </div>
        </div>
        
        <div class="contacts-title">Contacts</div>
        
        <div class="contacts-list">
            <?php while ($c = $contacts->fetch_assoc()): 
                $isActive = ($c['unique_number'] === $toNum) ? 'active' : '';
            ?>
              <a href="?to=<?= urlencode($c['unique_number']) ?>" class="contact <?= $isActive ?>">
                <?= htmlspecialchars($c['unique_number']) ?>
              </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="main">
        <div class="header">
            <div class="chat-icon">💬</div>
            <?= $toId ? "Chatting with " . htmlspecialchars($toNum) : 'Select a contact to start chatting' ?>
        </div>

        <?php if ($toId): ?>
            <div class="msgs">
                <?php while ($m = $history->fetch_assoc()): 
                    $cls = ($m['sender'] === $meNum) ? 'sent' : 'recv';
                ?>
                    <div class="msg <?= $cls ?>">
                        <?= nl2br(htmlspecialchars($m['message'])) ?>
                        <span class="ts"><?= $m['ts'] ?></span>
                    </div>
                <?php endwhile; ?>
            </div>

            <form method="post" class="input">
                <input type="text" name="message" placeholder="Type a message..." autocomplete="off" required>
                <button type="submit">➤</button>
            </form>
        <?php else: ?>
            <div class="no-chat">
                <div class="no-chat-icon">📱</div>
                <p>Select a contact from the sidebar to start chatting</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
