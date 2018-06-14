export default {
    get(url){
        return this.request(url)
    },
    post(url,value = null){
        return this.request(url,'post',value)
    },
    request(url, method = 'get',value = null) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open(method, url);
            xhr.onload = function () {
                if (this.status >= 200 && this.status < 300) {
                    resolve(JSON.parse(xhr.response));
                } else {
                    reject({
                        status: this.status,
                        statusText: xhr.statusText
                    })
                }
            }
            xhr.onerror = function () {
                reject({
                    status: this.status,
                    statusText: xhr.statusText
                })
            }
            xhr.send(value)
        })
    }
}