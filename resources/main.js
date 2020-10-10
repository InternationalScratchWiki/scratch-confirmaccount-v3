
$(function () {
    Array.prototype.forEach.call(document.getElementsByClassName('mw-scratch-confirmaccount-request-form'), el => {
        if (!el.shouldOpenScratchPage) return;
        if (el.shouldOpenScratchPage.value === '1') {
            el.addEventListener('submit', ev => {
                const profileLink = document.getElementById('mw-scratch-confirmaccount-profile-link');
                if (profileLink) profileLink.click();
            });
        }
    });
});

$(function () {
    Array.prototype.forEach.call(document.getElementsByClassName('mw-scratch-confirmaccount-bigselect'), el => {
        el.onchange = function (event) {
            const value = event.target.value;
            const textInput = document.getElementById(el.id.replace(/-dropdown$/, ''));
            textInput.value = value;
        };
    });
  });

$(function () { document.getElementById("mw-scratch-confirmaccount-click-copy").onclick = function() {
  copyToClipboard(document.getElementById("mw-scratch-confirmaccount-verifcode"));
  }
});

function copyToClipboard(temptext) {
        var tempItem = document.createElement('textarea');
        tempItem.value = temptext.innerText;
        tempItem.style.top = "0";
        tempItem.style.left = "0";
        tempItem.style.position = "fixed";
        document.body.appendChild(tempItem);
        tempItem.focus();
        tempItem.select();
        document.execCommand('copy');
        tempItem.parentElement.removeChild(tempItem);
}
