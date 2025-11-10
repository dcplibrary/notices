
CREATE VIEW ViewHoldNoticesData
AS
	SELECT
		CIR.ItemRecordID,
		CIR.AssignedBranchID,
		CIR.Barcode AS ItemBarcode, 
		BR.BrowseTitle AS BrowseTitle, 
		BR.BrowseAuthor AS BrowseAuthor, 
		IRD.CallNumber AS ItemCallNumber,
		IRD.CallNumberVolumeCopy AS  CallNumberVolumeCopy,
		IRD.Price AS Price, 
		ORG.Name AS Name, 
		ORG.Abbreviation AS Abbreviation, 
		CP.PhoneNumberOne AS PhoneNumberOne
	FROM 
		CircReserveItemRecords_View AS CIR (NOLOCK)
		INNER JOIN ItemRecordDetails AS IRD (NOLOCK)
			ON CIR.ItemRecordID = IRD.ItemRecordID
		INNER JOIN BibliographicRecords AS BR (NOLOCK)
			ON CIR.AssociatedBibRecordID = BR.BibliographicRecordID
		INNER JOIN Organizations AS ORG (NOLOCK)
			ON CIR.AssignedBranchID = ORG.OrganizationID
		LEFT OUTER JOIN SA_ContactPersons AS CP (NOLOCK)
			ON ORG.SA_ContactPersonID = CP.SA_ContactPersonID
