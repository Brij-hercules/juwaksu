/**
 * Google Sheets Meta Leads Integration Script
 * 
 * Instructions:
 * 1. Open your Google Sheet ("Wave City Meta Leads").
 * 2. Go to Extensions -> Apps Script.
 * 3. Delete any default code and paste this script in.
 * 4. Configure the CONFIG object below with your PHP API Endpoint URL and secure API Key.
 * 5. Save the project (click the floppy disk icon).
 * 6. Set up a Trigger:
 *    - Click the clock icon (Triggers) on the left sidebar.
 *    - Click "+ Add Trigger".
 *    - Choose "syncNewLeads" as the function to run.
 *    - Select Event source: "From spreadsheet"
 *    - Select Event type: "On edit" or "On change", or configure it as a "Time-driven" trigger to run every 5 minutes.
 */

const CONFIG = {
  // Replace with your actual PHP API endpoint URL
  API_URL: "https://primehashtag.com/api/google-sheet-lead.php", 
  
  // Replace with your actual API Key generated in your CRM setting
  API_KEY: "7fa2488fff8a436b091c0ee1276be8a0" 
};

/**
 * Main function to scan the Google Sheet and sync new leads to the PHP Database.
 */
function syncNewLeads() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  const lastRow = sheet.getLastRow();
  const lastColumn = sheet.getLastColumn();
  
  if (lastRow < 2) {
    Logger.log("No data rows found in the sheet.");
    return;
  }
  
  // 1. Read Header Row (Row 1)
  const headers = sheet.getRange(1, 1, 1, lastColumn).getValues()[0];
  
  // Normalize headers (trim, lowercase, replace spaces/special chars with underscores, strip trailing question marks)
  const normalizedHeaders = headers.map(header => {
    return header.toString()
      .trim()
      .toLowerCase()
      .replace(/[\s\?]+/g, '_')   // Replace spaces and question marks with underscores
      .replace(/_$/, '');         // Remove trailing underscores
  });
  
  // Find or create integration tracking columns: sync_status, sync_message, sync_time
  let syncStatusColIdx = normalizedHeaders.indexOf("sync_status") + 1;
  let syncMsgColIdx = normalizedHeaders.indexOf("sync_message") + 1;
  let syncTimeColIdx = normalizedHeaders.indexOf("sync_time") + 1;
  
  // If integration columns do not exist, append them to the sheet
  if (syncStatusColIdx === 0) {
    sheet.getRange(1, lastColumn + 1).setValue("sync_status");
    syncStatusColIdx = lastColumn + 1;
    headers.push("sync_status");
    normalizedHeaders.push("sync_status");
  }
  if (syncMsgColIdx === 0) {
    sheet.getRange(1, lastColumn + 2).setValue("sync_message");
    syncMsgColIdx = lastColumn + 2;
    headers.push("sync_message");
    normalizedHeaders.push("sync_message");
  }
  if (syncTimeColIdx === 0) {
    sheet.getRange(1, lastColumn + 3).setValue("sync_time");
    syncTimeColIdx = lastColumn + 3;
    headers.push("sync_time");
    normalizedHeaders.push("sync_time");
  }
  
  // Refetch last column index because we might have appended new columns
  const updatedLastColumn = sheet.getLastColumn();
  
  // 2. Fetch all data rows (Row 2 to lastRow)
  const dataRange = sheet.getRange(2, 1, lastRow - 1, updatedLastColumn);
  const dataValues = dataRange.getValues();
  
  Logger.log(`Scanning ${dataValues.length} rows for sync...`);
  
  // Prepare an array to hold our updates for the sync status columns
  // We will write these back all at once at the end to save execution time
  const statusUpdates = [];
  
  // 3. Process each row
  for (let i = 0; i < dataValues.length; i++) {
    const rowValues = dataValues[i];
    const currentRowNum = i + 2; // Rows are 1-indexed, starting from Row 2
    
    // Read status cell (0-indexed array, so idx is colIdx - 1)
    const currentSyncStatus = rowValues[syncStatusColIdx - 1] ? rowValues[syncStatusColIdx - 1].toString().trim().toLowerCase() : "";
    
    // Skip if already successfully synced or marked duplicate
    if (currentSyncStatus === "synced" || currentSyncStatus === "duplicate") {
      // Keep existing values in the update array to not overwrite with blank
      statusUpdates.push([
        rowValues[syncStatusColIdx - 1],
        rowValues[syncMsgColIdx - 1],
        rowValues[syncTimeColIdx - 1]
      ]);
      continue;
    }
    
    // Construct Lead JSON Payload dynamically using headers
    const leadPayload = {};
    for (let colIdx = 0; colIdx < headers.length; colIdx++) {
      const key = normalizedHeaders[colIdx];
      
      // Skip the integration status columns in the outgoing request payload
      if (key === "sync_status" || key === "sync_message" || key === "sync_time") {
        continue;
      }
      
      // Handle cellular value mapping
      let value = rowValues[colIdx];
      if (value instanceof Date) {
        value = value.toISOString(); // Format Date to ISO string
      }
      
      leadPayload[key] = value;
    }
    
    // Double check: do not send if ID or Phone is empty
    if (!leadPayload.id || !leadPayload.phone_number) {
      Logger.log(`Skipping Row ${currentRowNum}: Missing ID or Phone Number.`);
      statusUpdates.push(["failed", "Missing ID or Phone Number", new Date()]);
      continue;
    }
    
    Logger.log(`Syncing Row ${currentRowNum}: Lead ID ${leadPayload.id}...`);
    
    // 4. Send POST request to PHP API
    const response = sendLeadToAPI(leadPayload);
    
    // Push the result to our updates array
    statusUpdates.push([response.status, response.message, new Date()]);
  }
  
  // 5. Write all status updates back to the sheet in one batch operation!
  // This is MUCH faster than writing cell by cell
  if (statusUpdates.length > 0) {
    sheet.getRange(2, syncStatusColIdx, statusUpdates.length, 3).setValues(statusUpdates);
    SpreadsheetApp.flush();
  }
  
  Logger.log("Sync complete!");
}

/**
 * Sends a single lead payload to the PHP API endpoint.
 * Handles HTTP response status codes gracefully.
 */
function sendLeadToAPI(payload) {
  const options = {
    method: "post",
    contentType: "application/json",
    headers: {
      "X-API-Key": CONFIG.API_KEY
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true // Allows script to handle 4xx/5xx errors manually
  };
  
  try {
    const response = UrlFetchApp.fetch(CONFIG.API_URL, options);
    const responseCode = response.getResponseCode();
    const responseBody = response.getContentText();
    
    let parsedResponse = {};
    try {
      parsedResponse = JSON.parse(responseBody);
    } catch (e) {
      parsedResponse = { message: "Unknown server response" };
    }
    
    if (responseCode === 201) {
      return {
        status: "synced",
        message: parsedResponse.message || "Synced successfully"
      };
    } else if (responseCode === 409) {
      return {
        status: "duplicate",
        message: parsedResponse.message || "Lead already exists"
      };
    } else if (responseCode === 400) {
      return {
        status: "failed",
        message: "Validation Error: " + (parsedResponse.message || "Invalid data")
      };
    } else if (responseCode === 401) {
      return {
        status: "failed",
        message: "Authentication failed: Invalid API Key"
      };
    } else {
      return {
        status: "failed",
        message: `Server Error (${responseCode}): ` + (parsedResponse.message || "Internal server error")
      };
    }
    
  } catch (error) {
    Logger.log(`Fetch Error: ${error.toString()}`);
    return {
      status: "failed",
      message: "Connection failed: " + error.toString()
    };
  }
}
