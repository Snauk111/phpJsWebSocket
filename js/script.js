document.addEventListener('DOMContentLoaded', function () {
    let socket = new WebSocket("ws://phpjswebsocket:8090/server.php");
    socket.onopen = function () {
        getMessage("Connection open");
    }

    socket.onerror = function (error) {
        getMessage(`Error connection: ${error.message}`);
    }

    socket.onclose = function () {
        getMessage("Connection closed");
    }

    socket.onmessage = function (event) {
        let data = JSON.parse(event.data);
        getMessage(`${data.type} - ${data.message}`);
    }

    document.forms["pjws-form"].addEventListener('submit', onSubmit);

    function onSubmit(event) {
        event.preventDefault();
        let message = {
            client_message: this["client-message"].value,
            client_user: this["client-user"].value
        }

        this.setAttribute("type", "hidden");

        socket.send(JSON.stringify(message));

    }


});

function getMessage($message) {
    let br = document.createElement("br");
    window['message-result'].append($message);
    window['message-result'].appendChild(br);
}