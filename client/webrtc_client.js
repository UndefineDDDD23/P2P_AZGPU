const socket = new WebSocket("ws://localhost:8080/");
const localVideo = document.getElementById("localVideo");
const remoteVideosContainer = document.getElementById("remoteVideosContainer");
const roomIdInput = document.getElementById("roomIdInput");
const shareScreenButton = document.getElementById("shareScreen");
const toggleCameraButton = document.getElementById("toggleCamera");
const toggleMicButton = document.getElementById("toggleMic");

let roomId;
let peerConnections = {}; // Список RTCPeerConnection по участникам
let localStream;
let screenStream = null; // Поток экрана
let isCameraEnabled = true;
let isMicEnabled = true;

// Функция для получения параметров из URL
function getQueryParam(param) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(param);
}

// Обработка входящих сообщений WebSocket
socket.onmessage = (event) => {
  const data = JSON.parse(event.data);

  switch (data.type) {
    case 'new-peer':
      handleNewPeer(data.peerId);
      break;

    case 'signal':
      handleSignal(data.peerId, data.signalData);
      break;

    case 'peer-left':
      handlePeerLeft(data.peerId);
      break;

    // Ответ от сервера при создании комнаты
    case 'room-created':
      let roomUrlElement = document.getElementById("room_URL");
      roomUrlElement.href = data.url;
      roomUrlElement.innerText = data.url;
      console.log("Комната создана!\nRoom ID: " + data.roomId + "\nКлюч: " + data.secretKey + "\nURL: " + data.url);
      break;

    default:
      console.log("Неизвестный тип сообщения:", data.type);
  }
};

// Присоединение к комнате. Если в URL заданы roomId и key, то они используются.
async function joinRoom() {
  // Если в поле ввода ничего не введено, пытаемся взять roomId из URL
  roomId = roomIdInput.value || getQueryParam('roomId');
  const key = getQueryParam('key');

  if (!roomId || !key) {
    alert("Для подключения к комнате в URL должны быть заданы параметры roomId и key.");
    return;
  }

  try {
    localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
    localVideo.srcObject = localStream;
    console.log("Local video track: " + localStream.getVideoTracks()[0].id);
    // Передаём также секретный ключ
    socket.send(JSON.stringify({ type: "join-room", roomId, key }));
  } catch (error) {
    console.error("Ошибка получения локального видео/аудио:", error);
  }
}

// Создание нового соединения с участником (без изменений)
function handleNewPeer(peerId) {
  let peerConnection = peerConnections[peerId];
  if (!peerConnection) {
    peerConnection = createPeerConnection(peerId);
    peerConnections[peerId] = peerConnection;
  }
  console.log(`🎥 Используемый localStream ID: ${localStream.id}`);
  localStream.getTracks().forEach((track) => {
    console.log(`🎤 Добавление трека: ${track.id} (kind: ${track.kind}) в PeerConnection ${peerId}`);
    peerConnection.addTrack(track, localStream);
  });

  console.log("Generate offer and send video track to: " + peerId + " Track: " + localStream.getVideoTracks()[0].id);
  peerConnection.createOffer().then((offer) => {
    peerConnection.setLocalDescription(offer);
    console.log(offer);

    socket.send(JSON.stringify({
      type: "signal",
      roomId,
      targetId: peerId,
      signalData: offer
    }));
  }).catch((error) => console.error("Ошибка создания offer:", error));
}

// Обработка сигналинга (без изменений)
function handleSignal(peerId, signalData) {
  let peerConnection = peerConnections[peerId];
  if (!peerConnection) {
    peerConnection = createPeerConnection(peerId);
    peerConnections[peerId] = peerConnection;
  }

  if (signalData.type === "offer") {
    console.log(`Offer from ${peerId}: `);

    peerConnection.setRemoteDescription(new RTCSessionDescription(signalData));

    console.log(signalData);
    console.log(`🎥 Используемый localStream ID: ${localStream.id}`);
    localStream.getTracks().forEach((track) => {
      console.log(`🎤 Добавление трека: ${track.id} (kind: ${track.kind}) в PeerConnection ${peerId}`);
      peerConnection.addTrack(track, localStream);
    });

    console.log("Send answer to: " + peerId + " Track: " + localStream.getVideoTracks()[0].id);
    peerConnection.createAnswer().then((answer) => {
      peerConnection.setLocalDescription(answer);
      console.log(answer);

      socket.send(JSON.stringify({
        type: "signal",
        roomId,
        targetId: peerId,
        signalData: answer
      }));
    }).catch((error) => console.error("Ошибка создания answer:", error));
  } else if (signalData.type === "answer") {
    console.log(`📩 Получен ответ от ${peerId}:`, signalData);

    peerConnection.setRemoteDescription(new RTCSessionDescription(signalData))
      .then(() => console.log(`✅ Remote Description установлено для ${peerId}`))
      .catch(error => console.error(`❌ Ошибка setRemoteDescription:`, error));
  } else if (signalData.candidate) {
    peerConnection.addIceCandidate(new RTCIceCandidate(signalData)).catch((error) => {
      console.error("Ошибка добавления ICE-кандидата:", error);
    });
  }
}

// Обработка отключения участника (без изменений)
function handlePeerLeft(peerId) {
  const remoteVideo = document.getElementById(`remote-video-${peerId}`);
  if (remoteVideo) {
    remoteVideo.remove();
  }

  if (peerConnections[peerId]) {
    peerConnections[peerId].close();
    delete peerConnections[peerId];
  }
}

// Создание RTCPeerConnection (без изменений)
function createPeerConnection(peerId) {
  var configuration = {
    iceServers: [
      { 'url': 'stun:stun.l.google.com:19302' },
      {
        'url': 'turn:192.158.29.39:3478?transport=udp',
        'credential': 'JZEOEt2V3Qb0y27GRntt2u2PAYA=',
        'username': '28224511:1379330808'
      },
      {
        'url': 'turn:192.158.29.39:3478?transport=tcp',
        'credential': 'JZEOEt2V3Qb0y27GRntt2u2PAYA=',
        'username': '28224511:1379330808'
      }
    ],
    offerToReceiveAudio: true,
    offerToReceiveVideo: true,
  }
  const peerConnection = new RTCPeerConnection(configuration);

  peerConnection.ontrack = (event) => {
    if (event.track.kind === "video") {
      console.log(event.streams[0], event.track.id)
      const remoteVideo = document.createElement("video");
      remoteVideo.id = `remote-video-${peerId}`;
      remoteVideo.srcObject = event.streams[0];
      remoteVideo.autoplay = true;
      remoteVideo.muted = true;
      remoteVideosContainer.appendChild(remoteVideo);
    }
    if (event.track.kind === "audio") {
      // Обработка аудио-трека (при необходимости)
    }
  };

  peerConnection.onicecandidate = (event) => {
    if (event.candidate) {
      socket.send(JSON.stringify({
        type: "signal",
        roomId,
        targetId: peerId,
        signalData: event.candidate
      }));
    }
  };

  return peerConnection;
}

// Демонстрация экрана (без изменений)
async function shareScreen() {
  try {
    screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
    const screenTrack = screenStream.getTracks()[0];

    Object.values(peerConnections).forEach((peerConnection) => {
      const sender = peerConnection.getSenders().find((s) => s.track.kind === "video");
      if (sender) {
        sender.replaceTrack(screenTrack);
      }
    });

    screenTrack.onended = () => {
      stopScreenShare();
    };

    console.info("Демонстрация экрана начата");
  } catch (error) {
    console.error("Ошибка демонстрации экрана:", error);
  }
}

// Остановка демонстрации экрана (без изменений)
function stopScreenShare() {
  if (screenStream) {
    const videoTrack = localStream.getVideoTracks()[0];
    Object.values(peerConnections).forEach((peerConnection) => {
      const sender = peerConnection.getSenders().find((s) => s.track.kind === "video");
      if (sender) {
        sender.replaceTrack(videoTrack);
      }
    });
    screenStream.getTracks().forEach((track) => track.stop());
    screenStream = null;
    console.info("Демонстрация экрана остановлена");
  }
}

// Включение/выключение камеры (без изменений)
function toggleCamera() {
  isCameraEnabled = !isCameraEnabled;
  localStream.getVideoTracks()[0].enabled = isCameraEnabled;
  console.info("Камера " + (isCameraEnabled ? "включена" : "выключена"));
}

// Включение/выключение микрофона (без изменений)
function toggleMic() {
  isMicEnabled = !isMicEnabled;
  localStream.getAudioTracks()[0].enabled = isMicEnabled;
  console.info("Микрофон " + (isMicEnabled ? "включен" : "выключен"));
}

// Отключение от комнаты (без изменений)
function leaveRoom() {
  if (!roomId) return;

  socket.send(JSON.stringify({ type: "leave-room", roomId }));

  if (localStream) {
    localStream.getTracks().forEach((track) => track.stop());
  }

  Object.values(peerConnections).forEach((peerConnection) => peerConnection.close());
  peerConnections = {};

  roomId = null;
  console.info("Отключение от комнаты");
}

// Функция создания комнаты (панель администратора)
function createRoom() {
  const adminPassword = document.getElementById("adminPasswordInput").value;
  if (!adminPassword) {
    alert("Введите пароль администратора!");
    return;
  }
  socket.send(JSON.stringify({ type: "create-room", adminPassword }));
}

// Привязка событий кнопок
shareScreenButton.onclick = shareScreen;
toggleCameraButton.onclick = toggleCamera;
toggleMicButton.onclick = toggleMic;

setTimeout(() => {
  console.log(peerConnections);
}, 3000);
