var mysql = require("mysql");
const Web3 = require('web3');
const fs = require('fs');

const Config = JSON.parse(fs.readFileSync('/etc/block2trace/config.json'));

const contractAddress=Config[0].contractAddress;
const provider = new Web3(Config[0].url_web3);
const web3 = new Web3(provider);
const contractJson = fs.readFileSync(Config[0].abi);
const contractAbi = JSON.parse(contractJson);
const myContract = new web3.eth.Contract(contractAbi,contractAddress);

var timeout = 30000;

var con = mysql.createConnection({
    host: "localhost",
    user: Config[0].DB_USER,
    password: Config[0].DB_PASS,
    database: Config[0].DB_DBNAME
});

con.connect(function(err) {
        err ? console.log(err) : ""
});

setInterval(() => {
        
    var sql = "SELECT users.userId as userId, users.public_key as public_key, users.private_key as private_key, pendingDocs.pendingID as pendingID,pendingDocs.hash as hash,pendingDocs.documentdate as documentDate,pendingDocs.documentType as documentType,pendingDocs.tsa as hashTsa,pendingDocs.ipfshash as hashIpfs FROM users INNER JOIN pendingDocs ON users.userId=pendingDocs.userId WHERE (pendingDocs.upgrade='yes' AND pendingDocs.onBlockChain='yes') ORDER by pendingDocs.pendingID ASC limit 0,1";
    con.query(sql, function (err, result) {
            if (err) console.log(err);
            const rows = result.length;
            
            if (rows>0) {
                let userId=result[0].userId
                let fromAddress=result[0].public_key
                let privateKey=result[0].private_key
                let docHash=result[0].hash
                let docDate=result[0].documentDate
                let docType=result[0].documentType
                let docTsa=result[0].hashTsa
                let docIpfs=result[0].hashIpfs
                let pendingID=result[0].pendingID

                console.log('Sending '+docHash+' to SC...')
                
                const tx = {
                    from: fromAddress, 
                    to: contractAddress,
                    data: myContract.methods.UpdateDoc(docHash,docTsa,docDate,docIpfs).encodeABI() ,
                    gas : 9000000
                };
                
                const signPromise = web3.eth.accounts.signTransaction(tx, privateKey);
                signPromise.then((signedTx) => {  
                    const sentTx = web3.eth.sendSignedTransaction(signedTx.raw || signedTx.rawTransaction);  
                    sentTx.on("receipt", receipt => {
                        //console.log('TX Sent');
                        let d = new Date()
                        let day = d.getDate()
                        let month = d.getMonth()
                        let year = d.getFullYear()
                        let logDate = year+'-'+month+'-'+day;
                    
                        let content = '['+logDate+'] Tx Sent [UPDATE DOC '+docHash+']: '+receipt.transactionHash+'\n';
                        fs.writeFile('/var/log/block2trace.log',content, {flag: 'a+'}, err => {
                            if (err) console.log(err);
                        })
                    });
                    sentTx.on("error", err => {
                        //console.log("Tx Error Code 1\n"+err);
                        let d = new Date()
                        let day = d.getDate()
                        let month = d.getMonth()
                        let year = d.getFullYear()
                        let logDate = year+'-'+month+'-'+day;
                    
                        let content = '['+logDate+'] Tx Error: '+err+'\n';
                        fs.writeFile('/var/log/block2trace.log',content, {flag: 'a+'}, err => {
                            if (err) console.log(err);
                        })
                        
                    });
                
                    }).catch((err) => {
                        //console.log("TX Error Code 2");
                        let d = new Date()
                        let day = d.getDate()
                        let month = d.getMonth()
                        let year = d.getFullYear()
                        let logDate = year+'-'+month+'-'+day;
                    
                        let content = '['+logDate+'] Tx Error: '+err+'\n';
                        fs.writeFile('/var/log/block2trace.log',content, {flag: 'a+'}, err => {
                            if (err) console.log(err);
                        })
                    }
                );
                
                var sql2 = "UPDATE pendingDocs SET processed='yes',upgrade='no' WHERE pendingID="+pendingID;
                con.query(sql2, function (err, result) {
                    if (err) console.log(err)
                })
                
            }else{
                console.log('No new Data to Update');
            }
    });
                
}, timeout)