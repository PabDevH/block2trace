<html>
<head>
    <title>Block2Trace API Rest Help</title>
</head>
<body>
    <h3>Block2Trace API Rest Help</h3>
    <ul>
        <li><strong>Function: GetDoc</strong></li>
        <li>Check a Document in the Blockchain Smart Contract</li>
        <li>Method: GET</li>
        <li>Required Fields:<ul><li>file_hash as parameter (string) (sha512)</li></ul></li>
        <li>Response: Document Info in smart contract 
            <ul>
                <li>creationdate (uint)</li>
                <li>owner (address)</li>
                <li>documentdate (uint)</li>
                <li>docsaved (bool)</li>
                <li>counter (uint)</li>
                <li>documentType (string)</li>
                <li>updatedTimes (uint)</li>
                <li>tsa (string)</li>
                <li>ipfs (string)</li>
            </ul>
        </li>
    </ul>

    <ul>
        <li><strong>Function: CheckHistory</strong></li>
        <li>Check the Changes History of a Document in the Blockchain Smart Contract</li>
        <li>Method: GET</li>
        <li>Required Fields:<ul><li>file_hash (string) (sha512)</li></ul></li>
        <li>Response: Get Document Changes History
            <ul>
                <li>ChangeDate (uint[])</li>
                <li>PrevTSA (string[])</li>
                <li>PrevDocumentDate (uint[])</li>
            </ul>
        </li>
    </ul>

    <ul>
        <li><strong>Function: CreateTSA</strong></li>
        <li>Create a Time Stamp Authority to sign a Document, the result of this function can be use to Upload a Document in the Blockchain Smart Contract</li>
        <li>Method: POST</li>
        <li>Required Fields:<ul><li>file_hash (string) (sha512)</li></ul></li>
        <li>Response: Get Time Stamp Authority Document
            <ul>
                <li>file_hash (string)</li>
                <li>tsa_hash (string)</li>
                <li>date (int)</li>
                <li>tsa (string)</li>
            </ul>
        </li>
    </ul>
    <ul>
        <li><strong>Function: CreateIPFS</strong></li>
        <li>Save a TSA result in an IPFS Server, the result of this function can be use to Upload a Document in the Blockchain Smart Contract</li>
        <li>Method: POST</li>
        <li>Required Fields:
            <ul>
                <li>hash (string , TSA HASH) </li>
                <li>Tsa (string , TSR: use CreateTSA to get one) </li>
            </ul>
        </li>
        <li>Response: Get IPFS hash result
            <ul>
                <li>ipfs_hash (string)</li>
            </ul>
        </li>
    </ul>
    <ul>
        <li><strong>Function: UploadDoc</strong></li>
        <li>Upload a Document in the Blockchain Smart Contract</li>
        <li>Method: POST</li>
        <li>Required Fields:
            <ul>
                <li>hash (string , FILE HASH) </li>
                <li>documentdate (integer)</li>
                <li>documentType (string)</li>
            </ul>
        </li>
        <li>Optional Fields:
            <ul>
                <li>tsa (string , TSA HASH) </li>
                <li>tsa_hash (string)</li>
                <li>ipfs (string)</li>
            </ul>
        </li>
        <li>Response: 
            <ul>
                <li>error (integer) -> 0 valid</li>
                <li>error_result (string)</li>
            </ul>
        </li>
    </ul>
    <ul>
        <li><strong>Function: UploadDocandCreateTSA</strong></li>
        <li>Create a TSA, Upload the TSA Result in the IPFS, obtain a IPFS hash, and Upload the Document in the Blockchain Smart Contract</li>
        <li>Method: POST</li>
        <li>Required Fields:
            <ul>
                <li>hash (string , FILE HASH) </li>
                <li>documentdate (integer)</li>
                <li>documentType (string)</li>
            </ul>
        </li>
        <li>Response: 
            <ul>
                <li>error (integer) -> 0 valid</li>
                <li>error_result (string)</li>
            </ul>
        </li>
    </ul>
</body>
</html>