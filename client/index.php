<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Видеоконференция</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f0f0f0;
    }
    #main {
      padding: 20px;
    }
    #controls, #adminControls {
      margin-bottom: 20px;
    }
    #controls input, #controls button,
    #adminControls input, #adminControls button {
      padding: 10px;
      margin: 5px;
    }
    video {
      width: 100%;
      max-width: 300px;
      margin: 10px;
      border: 1px solid #ccc;
    }
    #remoteVideosContainer {
      display: flex;
      flex-wrap: wrap;
    }
  </style>
</head>
<body>
  <div id="main">
    <h1>Видеоконференция</h1>

    <!-- Панель администратора -->
    <div id="adminControls">
      <h2>Панель администратора</h2>
      <input type="password" id="adminPasswordInput" placeholder="Пароль администратора" />
      <a id="room_URL"></a>
      <button onclick="createRoom()">Создать комнату</button>
    </div>

    <!-- Основные элементы управления -->
    <div id="controls">
      <!-- Если пользователь не заходит по URL с параметрами, можно ввести roomId вручную -->
      <input type="text" id="roomIdInput" placeholder="Введите ID комнаты" />
      <button onclick="joinRoom()">Присоединиться</button>
      <button onclick="leaveRoom()">Покинуть комнату</button>
      <button id="shareScreen">Демонстрация экрана</button>
      <button id="toggleCamera">Включить/выключить камеру</button>
      <button id="toggleMic">Включить/выключить микрофон</button>
    </div>

    <div>
      <h3>Ваше видео:</h3>
      <video id="localVideo" autoplay muted></video>
    </div>
    <div>
      <h3>Видео участников:</h3>
      <div id="remoteVideosContainer"></div>
    </div>
  </div>

  <script src="webrtc_client.js"></script>
</body>
</html>
