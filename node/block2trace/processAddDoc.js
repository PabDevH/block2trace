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
var pendingIDS = [];
var docHash = []
var docDate = []
var docType = []
var docTsa = []
var docIpfs = []
var docHashTXT = ''


const removeEmpty = array => array.filter(str => typeof(str)==="string" ? Boolean(str.trim()) : true);


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
        var sqluserId = "SELECT users.userId as userId, users.public_key as public_key, users.private_key as private_key FROM users INNER JOIN pendingDocs ON users.userId=pendingDocs.userId WHERE (pendingDocs.upgrade='no' AND pendingDocs.processed='no') ORDER by pendingDocs.pendingID ASC limit 0,1 ";
        con.query(sqluserId, function(err,result) {
            if (err) console.log(err);
            let cRows = result.length;
                if (cRows>0) {
                    let userId=result[0].userId
                    let fromAddress=result[0].public_key
                    let privateKey=result[0].private_key
                    var sql = "SELECT pendingDocs.pendingID as pendingID,pendingDocs.hash as hash,pendingDocs.documentdate as documentDate,pendingDocs.documentType as documentType,pendingDocs.tsa as hashTsa,pendingDocs.ipfshash as hashIpfs  FROM pendingDocs WHERE (pendingDocs.upgrade='no' AND pendingDocs.processed='no' AND pendingDocs.userID="+userId+" ) ORDER by pendingDocs.last ASC limit 0,5";
                    con.query(sql, function (err, result) {
                            if (err) console.log(err);
                            const rows = result.length;
                            for (let j=0; j<rows; j++) {
                                //console.log(result[j].pendingID)
                                pendingIDS=result[j].pendingID+','+pendingIDS
                                
                                docHash=result[j].hash+','+docHash
                                docHashTXT=result[j].hash+','+docHashTXT
                                docDate=parseInt(result[j].documentDate)+','+docDate
                                docType=result[j].documentType+','+docType
                                docTsa=result[j].hashTsa+','+docTsa
                                docIpfs=result[j].hashIpfs+','+docIpfs
                            }
                            
                            if (rows>0) {
                                
                                pendingIDS=pendingIDS.split(",")
                                pendingIDS=removeEmpty(pendingIDS)
                                docHash=docHash.split(",")
                                docHash=removeEmpty(docHash)
                                docDate=docDate.split(",")
                                docDate=removeEmpty(docDate)
                                docType=docType.split(",")
                                docType=removeEmpty(docType)
                                docTsa=docTsa.split(",")
                                docTsa=removeEmpty(docTsa)
                                docIpfs=docIpfs.split(",")
                                docIpfs=removeEmpty(docIpfs)
                                console.log('Sending '+docHash+' to SC...')
                                
                                const tx = {
                                    from: fromAddress, 
                                    to: contractAddress,
                                    data: myContract.methods.MassAddDoc(docHash,docDate,docType,docTsa,docIpfs).encodeABI() ,
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
                                    
                                        let content = '['+logDate+'] Tx Sent: [AddDoc '+docHashTXT+'] '+receipt.transactionHash+'\n';
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
                                
                                
                                for (var x=0; x<pendingIDS.length;x++) {
                                    var sql2 = "UPDATE pendingDocs SET processed='yes',onBlockChain='yes' WHERE pendingID="+pendingIDS[x];
                                    con.query(sql2, function (err, result) {
                                        if (err) console.log(err)
                                    })
                                }

                                pendingIDS=[]
                                docHash=[]
                                docDate=[]
                                docType=[]
                                docTsa=[]
                                docIpfs=[]
                                docHashTXT=''
                            }else{
                                console.log('No new Data');
                            }
                    });
                }else{
                    console.log('No new Data')
                }
            });
}, timeout)