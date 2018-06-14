import './app.css'
import http from "./http";


let btn = document.getElementById("plugin-render")
let reqUrl = '/reward/query'
let resUrl = '/reward/order'
let checkOrderStatus
let total = document.createElement("input");
total.setAttribute("id","plugin-total");
total.type = "text";
total.placeholder = "单位是元，币种为人民币";
total.value = 9.9;

if (btn) {
    btn.addEventListener('click', function () {
        swal({
            title: "打赏",
            text: "感谢支持，请输入金额，然后点击下一步",
            icon: "success",
            buttons: {
                alipay: {
                    text: "支付宝",
                },
                wxpay: {
                    text: "扫微信",
                },
            },
            content: total,
        })
            .then(willDelete => {
                if (willDelete && total.value) {
                    http.get(reqUrl + '?total=' + total.value + '&type=' + willDelete)
                        .then(response => {
                            if (response.code == 'success') {
                                swal({
                                    title: response.data.title,
                                    text: '拿出小手机扫一扫',
                                    icon: response.data.url,
                                    buttons: {
                                        close: {
                                            text: "关闭",
                                        },
                                    },
                                    closeOnEsc: false,
                                    closeOnClickOutside: false,
                                })
                                    .then(res =>{
                                        if (res == 'close'){
                                            clearTimeout(checkOrderStatus)
                                            setTimeout("location.reload()", 5000)
                                        }
                                    })


                                checkOrderStatus = setInterval(function () {
                                    http.get(resUrl + '?id=' + response.data.id + '&type=' + willDelete)
                                        .then(response => {
                                            if (response.code == 'success') {
                                                clearTimeout(checkOrderStatus)
                                                setTimeout("location.reload()", 5000)
                                                swal({
                                                    title: "Good job!",
                                                    text: response.data.msg,
                                                    icon: "success",
                                                })
                                            }
                                        })
                                }, 3000)
                            }
                        })
                } else {
                    swal("取消打赏","嘤嘤嘤，为什么选择放弃","error")
                }
            })
    })
}
