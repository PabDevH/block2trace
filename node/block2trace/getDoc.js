const fs = require("fs");
const { isUndefined } = require("util");
// const Web3 = require("web3");

var Contract = require('web3-eth-contract');


const Config = JSON.parse(fs.readFileSync('/etc/block2trace/config.json'));

Contract.setProvider(Config[0].url_web3)

var contract_address = Config[0].contractAddress;

const contractJson = fs.readFileSync(Config[0].abi);

const contractAbi = JSON.parse(contractJson);
var contract = new Contract(contractAbi, contract_address);
var result = [];

//cambiar aca el valor a buscar
var hash_to_send = process.argv[2];

const callContract = async () => {
    try {
        var result = await contract.methods.Documents(hash_to_send).call()
    } catch(error) {
        console.log(error)
    } finally {
        return result;
    }
}

(async () => {
    if (contract_address==null || contract_address==undefined) {
        var error = [
            {
                'error':1,
                'errordesc':'Invalid Contract Address'
            }
        ]
        const response = JSON.stringify(error)
        console.log(response);
        return;
    }
    if (hash_to_send==null || contract_address==undefined) {
        var error = [
            {
                'error':1,
                'errordesc':'Please send a hash to search'
            }
        ]
        const response = JSON.stringify(error)
        console.log(response);
        return;
    }
    result = await callContract();
    if (result.owner=="0x0000000000000000000000000000000000000000") {
        var error = [
            {
                'error':1,
                'errordesc':'Document does not exists'
            }
        ]
        const response = JSON.stringify(error)
        console.log(response);
    }else{
        var error = [
            {
                'creationdate':result.creationdate,
                'owner':result.owner,
                'documentdate':result.documentdate,
                'docsaved':result.docsaved,
                'counter':result.counter,
                'documentType':result.documentType,
                'updatedTimes':result.updatedTimes,
                'tsa':result.tsa,
                'ipfs':result.ipfs
            }
        ]
        const response = JSON.stringify(error)
        console.log(response)
    }
    
})()

