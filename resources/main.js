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
        }
    });

$(function (){
    document.getElementById("mw-scratch-confirmaccount-clickCopy").onclick = function() {
    	copyToClipboard(document.getElementById("mw-scratch-confirmaccount-verifcode"));
    }

    function copyToClipboard(e) {
        var tempItem = document.createElement('input');

        tempItem.setAttribute('type','text');
        tempItem.setAttribute('display','none');

        let content = e;
        if (e instanceof HTMLElement) {
        		content = e.innerHTML;
        }

        tempItem.setAttribute('value',content);
        document.body.appendChild(tempItem);

        tempItem.select();
        document.execCommand('Copy');

        tempItem.parentElement.removeChild(tempItem);
    }
  });
