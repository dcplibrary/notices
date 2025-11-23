-- ═══════════════════════════════════════════════════════════════
-- POLARIS NOTIFICATION VERIFICATION - SQL QUERIES
-- ═══════════════════════════════════════════════════════════════
-- Version: 1.0
-- Date: November 19, 2025
-- System Owner: Brian Lashbrook, DC Public Library
-- 
-- Purpose: Ready-to-use SQL queries for notification verification
-- Usage: Replace @PatronID, @ItemRecordID, @SysHoldRequestID with actual values
-- ═══════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────
-- 1. PATRON LOOKUP QUERIES
-- ───────────────────────────────────────────────────────────────

-- 1.1: Find Patron by Barcode
-- Usage: When patron provides their library card
-- Returns: PatronID for use in other queries
-- ───────────────────────────────────────────────────────────────
SELECT 
    PatronID,
    Barcode AS PatronBarcode,
    PatronFullName,
    EmailAddress
FROM Polaris.Polaris.Patrons
WHERE Barcode = '21234567890001';  -- Replace with patron barcode
-- ───────────────────────────────────────────────────────────────


-- 1.2: Find Patron by Phone Number
-- Usage: When patron provides phone number
-- Returns: PatronID for use in other queries
-- ───────────────────────────────────────────────────────────────
SELECT 
    p.PatronID,
    p.Barcode AS PatronBarcode,
    p.PatronFullName,
    pr.PhoneVoice1,
    pr.PhoneVoice2,
    pr.PhoneVoice3,
    p.TxtPhoneNumber
FROM Polaris.Polaris.Patrons p
JOIN Polaris.Polaris.PatronRegistration pr ON p.PatronID = pr.PatronID
WHERE pr.PhoneVoice1 = '270-555-0101'
   OR pr.PhoneVoice2 = '270-555-0101'
   OR pr.PhoneVoice3 = '270-555-0101'
   OR p.TxtPhoneNumber = '270-555-0101';  -- Replace with phone number
-- ───────────────────────────────────────────────────────────────


-- 1.3: Get Complete Patron Contact Information
-- Usage: After finding PatronID, get all contact details
-- Returns: All contact info for verification
-- ───────────────────────────────────────────────────────────────
SELECT 
    p.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst,
    pr.NameLast,
    pr.NameMiddle,
    pr.NameTitle,
    p.EmailAddress,
    pr.PhoneVoice1,
    pr.PhoneVoice2,
    pr.PhoneVoice3,
    pr.PhoneFAX,
    p.TxtPhoneNumber,
    p.DeliveryOptionID,
    do.Description AS DeliveryMethod,
    p.EnableSMS,
    p.ExcludeFromOverdue,
    p.ExcludeFromBills,
    p.ExcludeFromPatronRecExpiration,
    pr.LanguageID,
    l.Description AS Language
FROM Polaris.Polaris.Patrons p
JOIN Polaris.Polaris.PatronRegistration pr ON p.PatronID = pr.PatronID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON p.DeliveryOptionID = do.DeliveryOptionID
LEFT JOIN Polaris.Polaris.Languages l ON pr.LanguageID = l.LanguageID
WHERE p.PatronID = 200001;  -- Replace with PatronID
-- ───────────────────────────────────────────────────────────────


-- ───────────────────────────────────────────────────────────────
-- 2. HOLD NOTIFICATION QUERIES
-- ───────────────────────────────────────────────────────────────

-- 2.1: Get All Recent Hold Notifications for Patron
-- Usage: See all hold notifications in last 30 days
-- Returns: Complete hold notification history
-- ───────────────────────────────────────────────────────────────
SELECT 
    hr.SysHoldRequestID,
    hr.RequestDate AS HoldPlacedDate,
    hr.FilledDate AS HoldFilledDate,
    hr.HoldTillDate AS HoldExpirationDate,
    hr.HoldNotificationDate,
    hn.ItemBarcode,
    hn.BrowseTitle,
    hn.BrowseAuthor,
    hn.ItemCallNumber,
    org.Name AS PickupBranch,
    org.Abbreviation AS BranchCode,
    nq.CreationDate AS NotificationQueuedDate,
    nq.DeliveryOptionID,
    do.Description AS NotificationMethod,
    nq.Processed AS WasProcessed,
    nl.NotificationDateTime AS ActualSentDate,
    nl.NotificationStatusID AS DeliveryStatusID,
    nst.Description AS DeliveryStatus,
    nl.DeliveryString AS SentToPhoneOrEmail,
    nl.Details AS DeliveryDetails
FROM Polaris.Polaris.SysHoldRequests hr
LEFT JOIN Results.Polaris.HoldNotices hn 
    ON hr.PatronID = hn.PatronID 
    AND hr.TrappingItemRecordID = hn.ItemRecordID
LEFT JOIN Polaris.Polaris.Organizations org 
    ON hr.PickupOrgID = org.OrganizationID
LEFT JOIN Results.Polaris.NotificationQueue nq 
    ON hr.PatronID = nq.PatronID 
    AND hr.TrappingItemRecordID = nq.ItemRecordID 
    AND nq.NotificationTypeID = 2
LEFT JOIN PolarisTransactions.Polaris.NotificationLog nl 
    ON hr.PatronID = nl.PatronID 
    AND nl.NotificationTypeID = 2
    AND CAST(nl.NotificationDateTime AS DATE) = CAST(nq.CreationDate AS DATE)
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do 
    ON nq.DeliveryOptionID = do.DeliveryOptionID
LEFT JOIN Polaris.Polaris.NotificationStatuses nst 
    ON nl.NotificationStatusID = nst.NotificationStatusID
WHERE hr.PatronID = 200001  -- Replace with PatronID
  AND hr.RequestDate >= DATEADD(day, -30, GETDATE())
ORDER BY hr.RequestDate DESC;
-- ───────────────────────────────────────────────────────────────


-- 2.2: Get Specific Hold Request Details
-- Usage: When you have SysHoldRequestID from submission file
-- Returns: Complete timeline for one hold
-- ───────────────────────────────────────────────────────────────
SELECT 
    hr.SysHoldRequestID,
    hr.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast AS PatronName,
    hr.RequestDate AS HoldPlacedDate,
    hr.ActivationDate,
    hr.FilledDate AS HoldFilledDate,
    hr.HoldNotificationDate,
    hr.HoldTillDate AS HoldExpirationDate,
    DATEDIFF(day, GETDATE(), hr.HoldTillDate) AS DaysUntilExpiration,
    hr.StatusID AS HoldStatusID,
    hs.Description AS HoldStatus,
    hr.BibliographicRecordID,
    hr.TrappingItemRecordID,
    cir.Barcode AS ItemBarcode,
    br.BrowseTitle,
    br.BrowseAuthor,
    cir.CallNumber,
    hr.PickupOrgID,
    org.Name AS PickupBranch,
    hr.PatronNotes,
    hr.StaffDisplayNotes
FROM Polaris.Polaris.SysHoldRequests hr
JOIN Polaris.Polaris.Patrons p ON hr.PatronID = p.PatronID
JOIN Polaris.Polaris.PatronRegistration pr ON hr.PatronID = pr.PatronID
LEFT JOIN Polaris.Polaris.CircItemRecords cir ON hr.TrappingItemRecordID = cir.ItemRecordID
LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
LEFT JOIN Polaris.Polaris.Organizations org ON hr.PickupOrgID = org.OrganizationID
LEFT JOIN Polaris.Polaris.HoldStatuses hs ON hr.StatusID = hs.StatusID
WHERE hr.SysHoldRequestID = 500001;  -- Replace with SysHoldRequestID
-- ───────────────────────────────────────────────────────────────


-- 2.3: Find Holds That Should Have Been Notified (But Weren't)
-- Usage: Proactive check for missing hold notifications
-- Returns: Holds that were filled but no notification queued
-- ───────────────────────────────────────────────────────────────
SELECT 
    hr.SysHoldRequestID,
    hr.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast AS PatronName,
    hr.FilledDate,
    hr.HoldTillDate,
    cir.Barcode AS ItemBarcode,
    br.BrowseTitle,
    org.Name AS PickupBranch,
    p.DeliveryOptionID,
    do.Description AS PreferredMethod,
    CASE 
        WHEN nq.NotificationQueueID IS NULL THEN '⚠️ NO NOTIFICATION QUEUED'
        ELSE 'Notification exists'
    END AS Status
FROM Polaris.Polaris.SysHoldRequests hr
JOIN Polaris.Polaris.Patrons p ON hr.PatronID = p.PatronID
JOIN Polaris.Polaris.PatronRegistration pr ON hr.PatronID = pr.PatronID
LEFT JOIN Polaris.Polaris.CircItemRecords cir ON hr.TrappingItemRecordID = cir.ItemRecordID
LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
LEFT JOIN Polaris.Polaris.Organizations org ON hr.PickupOrgID = org.OrganizationID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON p.DeliveryOptionID = do.DeliveryOptionID
LEFT JOIN Results.Polaris.NotificationQueue nq 
    ON hr.PatronID = nq.PatronID 
    AND hr.TrappingItemRecordID = nq.ItemRecordID 
    AND nq.NotificationTypeID = 2
WHERE hr.FilledDate >= DATEADD(day, -7, GETDATE())  -- Last 7 days
  AND hr.HoldTillDate > GETDATE()  -- Not expired
  AND hr.StatusID = 7  -- Status = Held (adjust based on your status codes)
  AND nq.NotificationQueueID IS NULL  -- No notification found
ORDER BY hr.FilledDate DESC;
-- ───────────────────────────────────────────────────────────────


-- ───────────────────────────────────────────────────────────────
-- 3. OVERDUE NOTIFICATION QUERIES
-- ───────────────────────────────────────────────────────────────

-- 3.1: Get All Recent Overdue Notifications for Patron
-- Usage: See all overdue notifications in last 60 days
-- Returns: Complete overdue notification history
-- ───────────────────────────────────────────────────────────────
SELECT 
    co.ItemRecordID,
    cir.Barcode AS ItemBarcode,
    br.BrowseTitle,
    br.BrowseAuthor,
    cir.CallNumber,
    mt.Description AS MaterialType,
    co.CheckOutDate,
    co.DueDate AS OriginalDueDate,
    co.RenewalCount,
    co.LastRenewalDate,
    co.LoanPeriodEndDate AS CurrentDueDate,
    DATEDIFF(day, co.LoanPeriodEndDate, GETDATE()) AS DaysOverdue,
    nq.CreationDate AS NotificationQueuedDate,
    nq.NotificationTypeID,
    nt.Description AS NotificationType,
    nq.DeliveryOptionID,
    do.Description AS NotificationMethod,
    nq.Processed AS WasProcessed,
    nl.NotificationDateTime AS ActualSentDate,
    nl.NotificationStatusID AS DeliveryStatusID,
    nst.Description AS DeliveryStatus,
    nl.DeliveryString AS SentToPhoneOrEmail,
    nl.Details AS DeliveryDetails,
    nh.Amount AS FineAmount
FROM Polaris.Polaris.ItemCheckouts co
JOIN Polaris.Polaris.CircItemRecords cir ON co.ItemRecordID = cir.ItemRecordID
LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
LEFT JOIN Polaris.Polaris.MaterialTypes mt ON cir.MaterialTypeID = mt.MaterialTypeID
LEFT JOIN Results.Polaris.NotificationQueue nq 
    ON co.PatronID = nq.PatronID 
    AND co.ItemRecordID = nq.ItemRecordID 
    AND nq.NotificationTypeID IN (1, 7, 12, 13)  -- Overdue types
LEFT JOIN PolarisTransactions.Polaris.NotificationLog nl 
    ON co.PatronID = nl.PatronID 
    AND nl.NotificationTypeID IN (1, 7, 12, 13)
    AND CAST(nl.NotificationDateTime AS DATE) = CAST(nq.CreationDate AS DATE)
LEFT JOIN Results.Polaris.NotificationHistory nh 
    ON co.PatronID = nh.PatronID 
    AND co.ItemRecordID = nh.ItemRecordId 
    AND nh.NotificationTypeId IN (1, 7, 12, 13)
LEFT JOIN Polaris.Polaris.NotificationTypes nt ON nq.NotificationTypeID = nt.NotificationTypeID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON nq.DeliveryOptionID = do.DeliveryOptionID
LEFT JOIN Polaris.Polaris.NotificationStatuses nst ON nl.NotificationStatusID = nst.NotificationStatusID
WHERE co.PatronID = 200001  -- Replace with PatronID
  AND co.LoanPeriodEndDate >= DATEADD(day, -60, GETDATE())
ORDER BY co.CheckOutDate DESC;
-- ───────────────────────────────────────────────────────────────


-- 3.2: Get Checkout and Renewal Timeline for Specific Item
-- Usage: When verifying overdue notification timing
-- Returns: Complete checkout history with all dates
-- ───────────────────────────────────────────────────────────────
SELECT 
    co.ItemRecordID,
    co.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast AS PatronName,
    cir.Barcode AS ItemBarcode,
    br.BrowseTitle,
    br.BrowseAuthor,
    co.CheckOutDate,
    co.DueDate AS OriginalDueDate,
    co.RenewalCount,
    co.LastRenewalDate,
    co.LoanPeriodEndDate AS CurrentDueDate,
    DATEDIFF(day, co.LoanPeriodEndDate, GETDATE()) AS DaysOverdue,
    co.OrganizationID AS CheckoutBranchID,
    org.Name AS CheckoutBranch,
    -- Calculate expected notification dates
    DATEADD(day, 1, co.LoanPeriodEndDate) AS ExpectedFirstOverdueDate,
    DATEADD(day, 8, co.LoanPeriodEndDate) AS ExpectedSecondOverdueDate,
    DATEADD(day, 22, co.LoanPeriodEndDate) AS ExpectedThirdOverdueDate
FROM Polaris.Polaris.ItemCheckouts co
JOIN Polaris.Polaris.Patrons p ON co.PatronID = p.PatronID
JOIN Polaris.Polaris.PatronRegistration pr ON co.PatronID = pr.PatronID
JOIN Polaris.Polaris.CircItemRecords cir ON co.ItemRecordID = cir.ItemRecordID
LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
LEFT JOIN Polaris.Polaris.Organizations org ON co.OrganizationID = org.OrganizationID
WHERE co.ItemRecordID = 300001  -- Replace with ItemRecordID
  AND co.PatronID = 200001;  -- Replace with PatronID
-- ───────────────────────────────────────────────────────────────


-- 3.3: Find Overdue Items That Should Have Been Notified (But Weren't)
-- Usage: Proactive check for missing overdue notifications
-- Returns: Items overdue but no notification queued
-- ───────────────────────────────────────────────────────────────
SELECT 
    co.ItemRecordID,
    co.PatronID,
    p.Barcode AS PatronBarcode,
    pr.NameFirst + ' ' + pr.NameLast AS PatronName,
    cir.Barcode AS ItemBarcode,
    br.BrowseTitle,
    co.LoanPeriodEndDate AS DueDate,
    DATEDIFF(day, co.LoanPeriodEndDate, GETDATE()) AS DaysOverdue,
    p.DeliveryOptionID,
    do.Description AS PreferredMethod,
    p.ExcludeFromOverdue,
    CASE 
        WHEN p.ExcludeFromOverdue = 1 THEN 'Patron opted out'
        WHEN nq.NotificationQueueID IS NULL THEN '⚠️ NO NOTIFICATION QUEUED'
        ELSE 'Notification exists'
    END AS Status
FROM Polaris.Polaris.ItemCheckouts co
JOIN Polaris.Polaris.Patrons p ON co.PatronID = p.PatronID
JOIN Polaris.Polaris.PatronRegistration pr ON co.PatronID = pr.PatronID
JOIN Polaris.Polaris.CircItemRecords cir ON co.ItemRecordID = cir.ItemRecordID
LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON p.DeliveryOptionID = do.DeliveryOptionID
LEFT JOIN Results.Polaris.NotificationQueue nq 
    ON co.PatronID = nq.PatronID 
    AND co.ItemRecordID = nq.ItemRecordID 
    AND nq.NotificationTypeID IN (1, 7, 12, 13)
WHERE co.LoanPeriodEndDate < DATEADD(day, -1, GETDATE())  -- Overdue by at least 1 day
  AND co.LoanPeriodEndDate >= DATEADD(day, -30, GETDATE())  -- Within last 30 days
  AND p.ExcludeFromOverdue = 0  -- Patron has not opted out
  AND nq.NotificationQueueID IS NULL  -- No notification found
ORDER BY co.LoanPeriodEndDate;
-- ───────────────────────────────────────────────────────────────


-- ───────────────────────────────────────────────────────────────
-- 4. NOTIFICATION STATUS QUERIES
-- ───────────────────────────────────────────────────────────────

-- 4.1: Get All Failed Notifications in Last 30 Days
-- Usage: Daily monitoring of notification failures
-- Returns: All notifications that failed delivery
-- ───────────────────────────────────────────────────────────────
SELECT 
    nl.NotificationLogID,
    nl.PatronID,
    nl.PatronBarcode,
    p.PatronFullName,
    nl.NotificationDateTime,
    nl.NotificationTypeID,
    nt.Description AS NotificationType,
    nl.DeliveryOptionID,
    do.Description AS DeliveryMethod,
    nl.DeliveryString AS AttemptedAddress,
    nl.NotificationStatusID,
    nst.Description AS FailureReason,
    nl.Details,
    nl.HoldsCount,
    nl.OverduesCount,
    nl.BillsCount
FROM PolarisTransactions.Polaris.NotificationLog nl
JOIN Polaris.Polaris.Patrons p ON nl.PatronID = p.PatronID
LEFT JOIN Polaris.Polaris.NotificationTypes nt ON nl.NotificationTypeID = nt.NotificationTypeID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON nl.DeliveryOptionID = do.DeliveryOptionID
LEFT JOIN Polaris.Polaris.NotificationStatuses nst ON nl.NotificationStatusID = nst.NotificationStatusID
WHERE nl.NotificationStatusID IN (4, 5, 6, 8, 10, 11, 13, 14)  -- Failed status codes
  AND nl.NotificationDateTime >= DATEADD(day, -30, GETDATE())
ORDER BY nl.NotificationDateTime DESC;
-- ───────────────────────────────────────────────────────────────


-- 4.2: Get Notification Success Rate by Delivery Method
-- Usage: Weekly/monthly reporting
-- Returns: Success vs failure rates
-- ───────────────────────────────────────────────────────────────
SELECT 
    nl.DeliveryOptionID,
    do.Description AS DeliveryMethod,
    COUNT(*) AS TotalSent,
    SUM(CASE WHEN nl.NotificationStatusID IN (1, 2, 3, 7, 9, 12, 15, 16) THEN 1 ELSE 0 END) AS Successful,
    SUM(CASE WHEN nl.NotificationStatusID IN (4, 5, 6, 8, 10, 11, 13, 14) THEN 1 ELSE 0 END) AS Failed,
    CAST(100.0 * SUM(CASE WHEN nl.NotificationStatusID IN (1, 2, 3, 7, 9, 12, 15, 16) THEN 1 ELSE 0 END) / COUNT(*) AS DECIMAL(5,2)) AS SuccessRate
FROM PolarisTransactions.Polaris.NotificationLog nl
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON nl.DeliveryOptionID = do.DeliveryOptionID
WHERE nl.NotificationDateTime >= DATEADD(day, -30, GETDATE())
GROUP BY nl.DeliveryOptionID, do.Description
ORDER BY nl.DeliveryOptionID;
-- ───────────────────────────────────────────────────────────────


-- 4.3: Get Notifications Queued But Not Yet Processed
-- Usage: Daily check for stuck notifications
-- Returns: Notifications pending in queue
-- ───────────────────────────────────────────────────────────────
SELECT 
    nq.NotificationQueueID,
    nq.PatronID,
    p.Barcode AS PatronBarcode,
    p.PatronFullName,
    nq.ItemRecordID,
    nq.NotificationTypeID,
    nt.Description AS NotificationType,
    nq.DeliveryOptionID,
    do.Description AS DeliveryMethod,
    nq.CreationDate,
    DATEDIFF(hour, nq.CreationDate, GETDATE()) AS HoursInQueue,
    nq.ReportingOrgID,
    org.Name AS Branch,
    CASE 
        WHEN DATEDIFF(hour, nq.CreationDate, GETDATE()) > 24 THEN '⚠️ STUCK - Over 24 hours'
        WHEN DATEDIFF(hour, nq.CreationDate, GETDATE()) > 12 THEN '⚠️ DELAYED - Over 12 hours'
        ELSE 'Normal'
    END AS Status
FROM Results.Polaris.NotificationQueue nq
JOIN Polaris.Polaris.Patrons p ON nq.PatronID = p.PatronID
LEFT JOIN Polaris.Polaris.NotificationTypes nt ON nq.NotificationTypeID = nt.NotificationTypeID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON nq.DeliveryOptionID = do.DeliveryOptionID
LEFT JOIN Polaris.Polaris.Organizations org ON nq.ReportingOrgID = org.OrganizationID
WHERE nq.Processed = 0  -- Not yet processed
ORDER BY nq.CreationDate;
-- ───────────────────────────────────────────────────────────────


-- ───────────────────────────────────────────────────────────────
-- 5. COMPREHENSIVE VERIFICATION QUERIES
-- ───────────────────────────────────────────────────────────────

-- 5.1: Complete Patron Notification History (All Types)
-- Usage: Main verification query - shows everything for a patron
-- Returns: Complete 30-day notification history
-- ───────────────────────────────────────────────────────────────
WITH NotificationTimeline AS (
    -- Hold notifications
    SELECT 
        'HOLD' AS NotificationType,
        hr.SysHoldRequestID AS ReferenceID,
        hr.PatronID,
        hr.RequestDate AS EventDate,
        'Hold Placed' AS Event,
        hn.BrowseTitle AS ItemTitle,
        hn.ItemBarcode,
        NULL AS DueDate,
        NULL AS DaysOverdue,
        NULL AS NotificationQueueDate,
        NULL AS NotificationSentDate,
        NULL AS DeliveryStatus
    FROM Polaris.Polaris.SysHoldRequests hr
    LEFT JOIN Results.Polaris.HoldNotices hn ON hr.PatronID = hn.PatronID AND hr.TrappingItemRecordID = hn.ItemRecordID
    WHERE hr.PatronID = 200001
      AND hr.RequestDate >= DATEADD(day, -30, GETDATE())
    
    UNION ALL
    
    SELECT 
        'HOLD',
        hr.SysHoldRequestID,
        hr.PatronID,
        hr.FilledDate,
        'Hold Filled',
        hn.BrowseTitle,
        hn.ItemBarcode,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL
    FROM Polaris.Polaris.SysHoldRequests hr
    LEFT JOIN Results.Polaris.HoldNotices hn ON hr.PatronID = hn.PatronID AND hr.TrappingItemRecordID = hn.ItemRecordID
    WHERE hr.PatronID = 200001
      AND hr.FilledDate >= DATEADD(day, -30, GETDATE())
    
    UNION ALL
    
    SELECT 
        'HOLD',
        hr.SysHoldRequestID,
        hr.PatronID,
        nq.CreationDate,
        'Notification Queued',
        hn.BrowseTitle,
        hn.ItemBarcode,
        NULL,
        NULL,
        nq.CreationDate,
        NULL,
        NULL
    FROM Polaris.Polaris.SysHoldRequests hr
    LEFT JOIN Results.Polaris.HoldNotices hn ON hr.PatronID = hn.PatronID AND hr.TrappingItemRecordID = hn.ItemRecordID
    LEFT JOIN Results.Polaris.NotificationQueue nq ON hr.PatronID = nq.PatronID AND hr.TrappingItemRecordID = nq.ItemRecordID AND nq.NotificationTypeID = 2
    WHERE hr.PatronID = 200001
      AND nq.CreationDate >= DATEADD(day, -30, GETDATE())
    
    UNION ALL
    
    SELECT 
        'HOLD',
        hr.SysHoldRequestID,
        hr.PatronID,
        nl.NotificationDateTime,
        'Notification Sent',
        hn.BrowseTitle,
        hn.ItemBarcode,
        NULL,
        NULL,
        NULL,
        nl.NotificationDateTime,
        nst.Description
    FROM Polaris.Polaris.SysHoldRequests hr
    LEFT JOIN Results.Polaris.HoldNotices hn ON hr.PatronID = hn.PatronID AND hr.TrappingItemRecordID = hn.ItemRecordID
    LEFT JOIN PolarisTransactions.Polaris.NotificationLog nl ON hr.PatronID = nl.PatronID AND nl.NotificationTypeID = 2
    LEFT JOIN Polaris.Polaris.NotificationStatuses nst ON nl.NotificationStatusID = nst.NotificationStatusID
    WHERE hr.PatronID = 200001
      AND nl.NotificationDateTime >= DATEADD(day, -30, GETDATE())
    
    UNION ALL
    
    -- Checkout events
    SELECT 
        'CHECKOUT',
        co.ItemRecordID,
        co.PatronID,
        co.CheckOutDate,
        'Item Checked Out',
        br.BrowseTitle,
        cir.Barcode,
        co.DueDate,
        NULL,
        NULL,
        NULL,
        NULL
    FROM Polaris.Polaris.ItemCheckouts co
    LEFT JOIN Polaris.Polaris.CircItemRecords cir ON co.ItemRecordID = cir.ItemRecordID
    LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
    WHERE co.PatronID = 200001
      AND co.CheckOutDate >= DATEADD(day, -60, GETDATE())
    
    UNION ALL
    
    -- Overdue notifications
    SELECT 
        'OVERDUE',
        co.ItemRecordID,
        co.PatronID,
        nq.CreationDate,
        'Overdue Notification Queued',
        br.BrowseTitle,
        cir.Barcode,
        co.LoanPeriodEndDate,
        DATEDIFF(day, co.LoanPeriodEndDate, GETDATE()),
        nq.CreationDate,
        NULL,
        NULL
    FROM Polaris.Polaris.ItemCheckouts co
    LEFT JOIN Polaris.Polaris.CircItemRecords cir ON co.ItemRecordID = cir.ItemRecordID
    LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
    LEFT JOIN Results.Polaris.NotificationQueue nq ON co.PatronID = nq.PatronID AND co.ItemRecordID = nq.ItemRecordID AND nq.NotificationTypeID IN (1, 7, 12, 13)
    WHERE co.PatronID = 200001
      AND nq.CreationDate >= DATEADD(day, -30, GETDATE())
)
SELECT *
FROM NotificationTimeline
ORDER BY EventDate DESC;
-- ───────────────────────────────────────────────────────────────


-- 5.2: Item-Level Verification (Complete Item Journey)
-- Usage: When patron asks about specific item
-- Returns: Complete timeline for one item
-- ───────────────────────────────────────────────────────────────
SELECT 
    'Item Details' AS Section,
    cir.ItemRecordID,
    cir.Barcode AS ItemBarcode,
    br.BrowseTitle,
    br.BrowseAuthor,
    cir.CallNumber,
    mt.Description AS MaterialType,
    NULL AS EventDate,
    NULL AS Event,
    NULL AS Details
FROM Polaris.Polaris.CircItemRecords cir
LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
LEFT JOIN Polaris.Polaris.MaterialTypes mt ON cir.MaterialTypeID = mt.MaterialTypeID
WHERE cir.ItemRecordID = 300001  -- Replace with ItemRecordID

UNION ALL

SELECT 
    'Checkout',
    co.ItemRecordID,
    cir.Barcode,
    br.BrowseTitle,
    br.BrowseAuthor,
    cir.CallNumber,
    NULL,
    co.CheckOutDate,
    'Checked out',
    'Due: ' + CONVERT(VARCHAR(10), co.DueDate, 120)
FROM Polaris.Polaris.ItemCheckouts co
JOIN Polaris.Polaris.CircItemRecords cir ON co.ItemRecordID = cir.ItemRecordID
LEFT JOIN Polaris.Polaris.BibliographicRecords br ON cir.BibliographicRecordID = br.BibliographicRecordID
WHERE co.ItemRecordID = 300001

UNION ALL

SELECT 
    'Notification Queue',
    nq.ItemRecordID,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    nq.CreationDate,
    'Notification queued',
    'Type: ' + nt.Description + ', Method: ' + do.Description
FROM Results.Polaris.NotificationQueue nq
LEFT JOIN Polaris.Polaris.NotificationTypes nt ON nq.NotificationTypeID = nt.NotificationTypeID
LEFT JOIN Polaris.Polaris.SA_DeliveryOptions do ON nq.DeliveryOptionID = do.DeliveryOptionID
WHERE nq.ItemRecordID = 300001

UNION ALL

SELECT 
    'Notification Log',
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    nl.NotificationDateTime,
    'Notification sent',
    'Status: ' + nst.Description + ', To: ' + nl.DeliveryString
FROM PolarisTransactions.Polaris.NotificationLog nl
LEFT JOIN Polaris.Polaris.NotificationStatuses nst ON nl.NotificationStatusID = nst.NotificationStatusID
WHERE nl.PatronID IN (
    SELECT PatronID FROM Polaris.Polaris.ItemCheckouts WHERE ItemRecordID = 300001
)

ORDER BY EventDate DESC;
-- ───────────────────────────────────────────────────────────────


-- ═══════════════════════════════════════════════════════════════
-- END OF SQL QUERIES
-- ═══════════════════════════════════════════════════════════════
