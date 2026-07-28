function assistantAppend(question, answer) {
    var log = document.getElementById('assistantLog');
    if (!log) return;
    var div = document.createElement('div');
    div.className = 'assistant-msg';
    div.innerHTML = '<div class="q">' + question.replace(/</g, '&lt;') + '</div><div class="a">' + answer.replace(/</g, '&lt;') + '</div>';
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
}

function assistantAsk(question) {
    var formData = new URLSearchParams();
    formData.append('question', question);
    formData.append('csrf_token', window.CSRF_TOKEN);

    fetch(window.API_BASE + '/api/assistant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString(),
    }).then(function (r) { return r.json(); })
      .then(function (data) {
        assistantAppend(question, data.answer || data.error || 'No response.');
      })
      .catch(function () {
        assistantAppend(question, 'Something went wrong reaching the assistant.');
      });
}

function assistantSubmit(event) {
    event.preventDefault();
    var input = document.getElementById('assistantInput');
    var question = input.value.trim();
    if (!question) return false;
    assistantAsk(question);
    input.value = '';
    return false;
}
