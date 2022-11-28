// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;


contract Block2Trace {

    struct Document {
        uint creationdate;
        address owner;
        uint documentdate;
        bool docsaved;
        uint counter;
        string documentType;
        uint updatedTimes;
        string tsa;
        string ipfs;
    }

    struct DocumentHistoryChanges {
        uint ChangeDate;
        string PrevTsa;
        uint PrevDocumentDate;
        string PrevIpfs;
    }

    mapping(string=>Document) public Documents;   //Array de documentos apuntando a la estructura
    mapping(address=>bool) certifiedAddress; //Certificacion de direcciones
    mapping(string=>mapping(uint=>DocumentHistoryChanges)) DocHistory; //Historial de Cambios
   
    uint DocCounter; //Cantidad de documentos subidos
    address owner;

    mapping(address=>uint) DocCounterbyAddress; //Cantidad de Documentos subidos x Direccion Certificada
    mapping(address=>uint) MaxDocsPerAddress; // Cantidad maxima de Documentos por Direccion
    mapping(address=>string) WhoIsAddress; // Identificador por direccion

    constructor() {
        owner = msg.sender;
    }

    event Newdoc(string _hash, address _owner);
    event ErrorAddDoc(string _hash, address _owner);

    modifier OnlyOwner() {
        require(msg.sender==owner, string("Not the Owner"));
        _;
    }

    modifier OnlyCertified() {
        require(certifiedAddress[msg.sender]==true, string("Not Authorized"));
        _;
    }

    function Whois(address _address) external view returns(string memory) {
        require(certifiedAddress[_address]==true, string("Address not in the certified list"));
        return(WhoIsAddress[_address]);
    }

    function WhoisOwner(string memory _hash) external view returns(string memory) {
        require(Documents[_hash].docsaved==true, string("Invalid Document"));
        address _addressDocOwner = Documents[_hash].owner;
        return(WhoIsAddress[_addressDocOwner]);
    }

    function AddCertified(address _address,uint _Max, string memory _whois) external OnlyOwner {
        require(certifiedAddress[_address]==false, string("This address is already certified"));
        certifiedAddress[_address]=true;
        MaxDocsPerAddress[_address]=_Max;
        WhoIsAddress[_address]=_whois;
    }

    function DelCertified(address _address) external OnlyOwner {
        require(certifiedAddress[_address]==true, string("Address not in the certified list"));
        certifiedAddress[_address]=false;
        MaxDocsPerAddress[_address]=0;
    }

    function EditCertified(address _address, uint _NewMax) external OnlyOwner {
        require(certifiedAddress[_address]==true, string("Address not in the certified list"));
        require(_NewMax>DocCounterbyAddress[_address],string("_NewMax must be greater than current"));
        MaxDocsPerAddress[_address]=_NewMax;
    }

    function AddDoc( string memory _hash, uint _documentdate,string memory _documentType, string memory _tsa, string memory _ipfs) public OnlyCertified {
        require(Documents[_hash].docsaved==false, string("Document Hash Already exists"));
        require(DocCounterbyAddress[msg.sender]+1<MaxDocsPerAddress[msg.sender],string("Out of limit"));
        uint _now;
        _now = block.timestamp;
        DocCounter++;
        DocCounterbyAddress[msg.sender]++;
        Documents[_hash]=Document({
            creationdate: uint(_now),
            owner: address(msg.sender),
            documentdate: uint(_documentdate),
            docsaved: bool(true),
            counter: uint(DocCounter),
            documentType: string(_documentType),
            updatedTimes: uint(0),
            tsa: string(_tsa),
            ipfs: string(_ipfs)
        });
        emit Newdoc(_hash, msg.sender);
    }

    function MassAddDoc(string[] memory _hash, uint[] memory _documentdate,string[] memory _documentType, string[] memory _tsa, string[] memory _ipfs) external OnlyCertified {
        uint total_hash = _hash.length;
        uint total_documentdate = _documentdate.length;
        uint total_documentType = _documentType.length;
        uint total_tsa = _tsa.length;
        uint total_ipfs = _ipfs.length;
        require(total_hash==total_documentdate,string("Error Hash or Document Date quantity are not equals"));
        require(total_documentdate==total_documentType,string("Error Document Date or Document Type quantity are not equals"));
        require(total_documentType==total_tsa,string("Error Document Type or TSA quantity are not equals"));
        require(total_tsa==total_ipfs,string("Error TSA or IPFS quantity are not equals"));
        require(total_hash<=5,string("The quantity must be < 6"));
        require(DocCounterbyAddress[msg.sender]+total_hash<MaxDocsPerAddress[msg.sender],string("Out of limit"));
        for (uint i=0; i<total_hash;i++) {
            if (Documents[_hash[i]].docsaved==false) {
                AddDoc(_hash[i],_documentdate[i],_documentType[i],_tsa[i],_ipfs[i]);
            }else{
                emit ErrorAddDoc(_hash[i], msg.sender);
            }
        }
    }

    function UpdateDoc(string memory _hash, string memory _tsa, uint _documentdate, string memory _ipfs) external OnlyCertified {
        require(Documents[_hash].owner==msg.sender,string("No the document Owner"));
        require(Documents[_hash].updatedTimes<2,string("Update limit reached"));
        uint _previousDocDate = Documents[_hash].documentdate;
        require(_documentdate<_previousDocDate,string("The update date of the new document must be prior to the previous one."));
        uint Times = Documents[_hash].updatedTimes;
        uint NewTimes = Times+1;
        string memory _PrevTsa = Documents[_hash].tsa;
        uint _PrevDocumentDate = Documents[_hash].documentdate;
        string memory _PrevIpfs = Documents[_hash].ipfs;

        uint _now = block.timestamp;

        DocHistory[_hash][Times]=DocumentHistoryChanges({
            ChangeDate: uint(_now),
            PrevTsa: string(_PrevTsa),
            PrevDocumentDate: uint(_PrevDocumentDate),
            PrevIpfs: string(_PrevIpfs)
        });

        Documents[_hash].tsa=_tsa;
        Documents[_hash].documentdate=_documentdate;
        Documents[_hash].updatedTimes=NewTimes;
        Documents[_hash].ipfs=_ipfs;
    }

    
    function getDocCount() external view returns(uint) {
        return DocCounter;
    }

    function getDocCountbyAddress(address _address) external view returns(uint) {
        return DocCounterbyAddress[_address];
    }

    function getAvailableDocsToUpload(address _address) external view returns(uint) {
        return MaxDocsPerAddress[_address]-DocCounterbyAddress[_address];
    }

    function CheckHistory(string memory _hash) external view returns(uint[] memory, string[] memory, uint[] memory, string[] memory) {
        uint _Max = Documents[_hash].updatedTimes;
        uint j;
        uint[] memory RR_ChangeDate = new uint[](_Max);
        uint[] memory RR_PrevDocumentDate = new uint[](_Max);
        string[] memory RR_PrevTsa = new string[](_Max);
        string[] memory RR_PrevIPFS = new string[](_Max);
        for (uint i=0; i<_Max; i++) {
            RR_ChangeDate[j]=DocHistory[_hash][i].ChangeDate;
            RR_PrevDocumentDate[j]=DocHistory[_hash][i].PrevDocumentDate;
            RR_PrevTsa[j]=DocHistory[_hash][i].PrevTsa;
            RR_PrevIPFS[j]=DocHistory[_hash][i].PrevIpfs;
            j++;
        }
        return(RR_ChangeDate,RR_PrevTsa,RR_PrevDocumentDate,RR_PrevIPFS);
    }

    
}