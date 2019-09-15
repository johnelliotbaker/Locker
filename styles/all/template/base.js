var Locker = {};
Locker.tpl = {};
Locker.tpl['after'] = `
<i class="fa fa-clipboard locker noselect pointer" aria-hidden="true" onClick="Locker.copy_element_text('unlocked_HASH')"></i>
<span id="unlocked_HASH">TEXT</span>
`;

Locker.copy_element_text = function(elem_id)
{
    var $elem = $('#' + elem_id);
    var text = $elem.text();
    Clipboard.copy(text);
}

Locker.generate_clipboard = function(hash, text)
{
    var tpl = this.tpl['after'];
    tpl = tpl.replace(/TEXT/g, text);
    tpl = tpl.replace(/HASH/g, hash);
    return tpl;
}

$(function () {
    var $lock = $('span[id*=locker_]');
    $lock.each((id)=>{
        $elem = $($lock[id]);
        $elem.click((event)=>{
            $target = $(event.target).closest('span');
            var hash = $target[0].dataset.hash;
            var url = '/app.php/locker/unlock/?h=' + hash;
            $.get(url).done((resp)=>{
                $target.unbind();
                $target.removeClass('noselect');
                $target.removeClass('pointer');
                if (resp.status===1)
                {
                    $target.html(Locker.generate_clipboard(hash, resp.text));
                }
                else
                {
                    $target.html('Invalid Data');
                }
            })
        })
    });
});
