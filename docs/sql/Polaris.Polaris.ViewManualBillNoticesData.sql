
CREATE VIEW ViewManualBillNoticesData
AS
	SELECT
		PA.ItemRecordID,
		PA.PatronID,
		CIR.Barcode AS ItemBarcode,
		CASE
			WHEN PA.ItemRecordID IS NOT NULL AND CIR.ItemRecordID IS NOT NULL THEN BR.BrowseTitle
			WHEN PA.ItemRecordID IS NOT NULL AND DEL.ItemRecordID IS NOT NULL THEN ISNULL(N'[DELETED]' + DEL.BrowseTitle, N'[DELETED]')
			ELSE NULL
		END AS BrowseTitle,
		CASE
			WHEN PA.ItemRecordID IS NOT NULL AND CIR.ItemRecordID IS NOT NULL THEN BR.BrowseAuthor
			WHEN PA.ItemRecordID IS NOT NULL AND DEL.ItemRecordID IS NOT NULL THEN ISNULL(N'[DELETED]' + DEL.BrowseAuthor, N'[DELETED]')
			ELSE NULL
		END AS BrowseAuthor,
		IRD.CallNumberVolumeCopy,
		PR.AdminLanguageID,
		ISNULL(CIR.MaterialTypeID, MT.MaterialTypeID) AS MaterialTypeID,
		PA.FeeReasonCodeID,
		PA.TxnDate,
		PA.OutstandingAmount,
		PA.TxnID,
		PA.OrganizationID AS ChargingOrgID,
		ORG.Abbreviation AS ChargingOrgAbbr,
		ORG.Name AS ChargingOrgName,
		CP.PhoneNumberOne AS ChargingOrgPhone
	FROM PatronAccount AS PA WITH (NOLOCK)
	INNER JOIN ViewPatronRegistration PR WITH (NOLOCK)
		ON PA.PatronID = PR.PatronID
	INNER JOIN Organizations AS ORG WITH (NOLOCK)
		ON PA.OrganizationID = ORG.OrganizationID
	INNER JOIN SA_ContactPersons AS CP WITH (NOLOCK)
		ON ORG.SA_ContactPersonID = CP.SA_ContactPersonID
	LEFT OUTER JOIN CircReserveItemRecords_View AS CIR WITH (NOLOCK) 
		ON PA.ItemRecordID = CIR.ItemRecordID
	LEFT OUTER JOIN ItemRecordDetails AS IRD WITH (NOLOCK)
		ON CIR.ItemRecordID = IRD.ItemRecordID
	LEFT OUTER JOIN ItemCheckouts AS IC WITH (NOLOCK) 
		ON CIR.ItemRecordID = IC.ItemRecordID
	LEFT OUTER JOIN BibliographicRecords AS BR WITH (NOLOCK)
		ON CIR.AssociatedBibRecordID = BR.BibliographicRecordID
	LEFT OUTER JOIN PatronAcctDeletedItemRecords DEL WITH (NOLOCK)
		ON (PA.ItemRecordID = DEL.ItemRecordID)
	LEFT OUTER JOIN MaterialTypes MT WITH (NOLOCK)
		ON (DEL.MaterialType = MT.[Description])
	WHERE PA.TxnCodeID = 1
