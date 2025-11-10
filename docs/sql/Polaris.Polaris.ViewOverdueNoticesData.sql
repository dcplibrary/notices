
CREATE VIEW ViewOverdueNoticesData
AS
	SELECT
		CIR.ItemRecordID,
		CIR.ItemStatusID, 
		IC.PatronID, 
		CIR.Barcode AS ItemBarcode, 
		IC.DueDate AS DueDate, 
		BR.BrowseTitle AS BrowseTitle, 
		BR.BrowseAuthor AS BrowseAuthor, 
		IRD.CallNumber AS ItemCallNumber,
		IRD.CallNumberVolumeCopy AS  CallNumberVolumeCopy,
		IRD.Price AS Price, 
		ORG.Name AS Name, 
		ORG.Abbreviation AS Abbreviation, 
		CP.PhoneNumberOne AS PhoneNumberOne,
		IC.OrganizationID AS LoaningOrganizationID,
		CIR.FineCodeID AS FineCodeID,--added for billing
		IC.LoanUnits AS LoanUnits,--added for billing
		IC.OvdNoticeCount,
		PR.AdminLanguageID 
	FROM 
		CircReserveItemRecords_View AS CIR WITH (NOLOCK) 
		INNER JOIN ItemRecordDetails AS IRD WITH (NOLOCK)
			ON CIR.ItemRecordID = IRD.ItemRecordID
		INNER JOIN ItemCheckouts AS IC WITH (NOLOCK) 
			ON CIR.ItemRecordID = IC.ItemRecordID
		INNER JOIN BibliographicRecords AS BR WITH (NOLOCK)
			ON CIR.AssociatedBibRecordID = BR.BibliographicRecordID
		INNER JOIN Organizations AS ORG WITH (NOLOCK)
			ON CIR.AssignedBranchID = ORG.OrganizationID
		INNER JOIN SA_ContactPersons AS CP WITH (NOLOCK)
			ON ORG.SA_ContactPersonID = CP.SA_ContactPersonID
		INNER JOIN ViewPatronRegistration PR WITH (NOLOCK)
			ON (IC.PatronID = PR.PatronID)
