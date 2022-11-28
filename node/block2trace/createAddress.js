/*
npm i ethereumjs-wallet
*/
var Wallet = require('ethereumjs-wallet');
const EthWallet = Wallet.default.generate();
console.log(EthWallet.getAddressString()+','+EthWallet.getPrivateKeyString());

