const fs = require("fs");
// const Web3 = require("web3");

var Contract = require('web3-eth-contract');
const Config = JSON.parse(fs.readFileSync('/etc/block2trace/config.json'));

Contract.setProvider(Config[0].url_web3);

//cambiar aca el valor
var contract_address = Config[0].contractAddress;
const contractJson = fs.readFileSync(Config[0].abi);
const contractAbi = JSON.parse(contractJson);
var contract = new Contract(contractAbi, contract_address);
var error = []
var result = []

//cambiar aca el valor a buscar
var hash_to_send = process.argv[2];

const callContract = async () => {
  try {
      var result = await contract.methods.CheckHistory(hash_to_send).call()
  } catch(error) {
      console.log(error)
  } finally {
      return result;
  }
}

(async () => {
  if (contract_address==null || contract_address==undefined) {
      error = [
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
      error = [
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
  var tmpArray = []
  if (result[0].length>0) {
    for (let j=0; j<result[0].length; j++) {
      tmpArray[j] = [
        {
          'ChangeDate':result[0][j],
          'PrevTSA':result[1][j],
          'PrevDocumentDate':result[2][j],
          'PrevIPFS':result[3][j]
        }
      ]
    }
    error = tmpArray;
  }else{
    error = [
      {
          'error':1,
          'errordesc':'No History'
      }
  ]
  }
  const response = JSON.stringify(error)
  console.log(response)
})()