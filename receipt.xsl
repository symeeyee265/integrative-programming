<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" doctype-system="about:legacy-compat" encoding="UTF-8" indent="yes"/>
    
    <xsl:template match="/">
        <html lang="en">
            <head>
                <meta charset="UTF-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
                <title>Vote Receipt - EduVote</title>
                <style>
                    body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    color: #333;
                    }
                    .receipt-container {
                    border: 2px solid #1a5276;
                    border-radius: 8px;
                    padding: 20px;
                    margin-top: 20px;
                    }
                    .receipt-header {
                    text-align: center;
                    margin-bottom: 20px;
                    color: #1a5276;
                    }
                    .receipt-details {
                    margin-bottom: 20px;
                    }
                    .receipt-id {
                    font-family: monospace;
                    background: #f5f5f5;
                    padding: 5px;
                    border-radius: 4px;
                    word-break: break-all;
                    }
                    .choices-list {
                    margin-top: 15px;
                    list-style-type: none;
                    padding: 0;
                    }
                    .choices-list li {
                    margin-bottom: 10px;
                    padding: 10px;
                    background: #f9f9f9;
                    border-left: 4px solid #1a5276;
                    }
                    .print-btn {
                    background: #1a5276;
                    color: white;
                    border: none;
                    padding: 10px 15px;
                    border-radius: 4px;
                    cursor: pointer;
                    margin-top: 20px;
                    font-size: 16px;
                    }
                    @media print {
                    .print-btn { display: none; }
                    body { padding: 0; }
                    .receipt-container { border: none; }
                    }
                </style>
            </head>
            <body>
                <div class="receipt-container">
                    <div class="receipt-header">
                        <h1>Vote Confirmation Receipt</h1>
                        <p>EduVote System</p>
                    </div>
                
                    <div class="receipt-details">
                        <p>
                            <strong>Voter:</strong> 
                            <xsl:value-of select="receipt/voter"/>
                        </p>
                        <p>
                            <strong>Election:</strong> 
                            <xsl:value-of select="receipt/election"/>
                        </p>
                        <p>
                            <strong>Date:</strong> 
                            <xsl:value-of select="receipt/date"/>
                        </p>
                        <p>
                            <strong>Receipt ID:</strong> 
                            <span class="receipt-id">
                                <xsl:value-of select="receipt/receipt_id"/>
                            </span>
                        </p>
                    </div>
                
                    <div class="choices-section">
                        <h3>Your Choices:</h3>
                        <ul class="choices-list">
                            <xsl:for-each select="receipt/choices/item">
                                <li>
                                    <strong>
                                        <xsl:value-of select="position"/>:</strong>
                                    <xsl:value-of select="choice"/>
                                </li>
                            </xsl:for-each>
                        </ul>
                    </div>
                
                    <p>This receipt confirms your vote has been recorded. Please keep this for your records.</p>
                    <p>If you believe there is an error, contact support with your receipt ID.</p>
                
                    <button class="print-btn" onclick="window.print()">Print Receipt</button>
                    <button class="print-btn" onclick="window.location.href='homePage.php'" style="margin-left: 10px;">🏠 Home</button>

                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>