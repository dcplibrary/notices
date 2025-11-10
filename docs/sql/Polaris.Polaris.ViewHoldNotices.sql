CREATE VIEW ViewHoldNotices
AS
	SELECT
		PR.PatronID, 
		P.Barcode AS PatronBarcode,
		PR.NameFirst AS PatronNameFirst, 
		PR.NameLast AS PatronNameLast, 
		PR.NameMiddle AS PatronNameMiddle, 
		NT.[Description] AS PatronNameTitle, 
		PR.NameSuffix  AS PatronNameSuffix, 
		PR.PhoneVoice1 AS PatronPhoneVoice, 
		PR.PhoneFAX AS PatronPhoneFAX, 
		PR.EmailAddress AS PatronEmailAddress, 
		VPA.AddressID AS PatronAddressID, 
		VPA.AddressTypeID AS PatronAddressTypeID, 
		VPA.StreetOne AS PatronStreetOne, 
		VPA.StreetTwo AS PatronStreetTwo, 
		VPA.City AS PatronCity, 
		VPA.State AS PatronState, 
		VPA.PostalCode + 
			case 
				when VPA.ZipPlusFour is not null and VPA.CountryID = 1 then N'-' + VPA.ZipPlusFour
				when VPA.ZipPlusFour is not null and VPA.CountryID > 1 then N' ' + VPA.ZipPlusFour
				else N''
			end
		AS PatronPostalCode, 
		VPA.ZipPlusFour AS PatronZipPlusFour, 
		ORG.OrganizationID, 
		ORG.Name AS OrgName, 
		ORG.Abbreviation AS OrgAbbreviation, 
		CP.PhoneNumberOne AS OrgPhoneVoice,
		CP.FaxNumber AS OrgPhoneFAX,
		CP.EmailAddress AS OrgEmailAddress,
		VOA.StreetOne AS OrgStreetOne, 
		VOA.StreetTwo AS OrgStreetTwo, 
		VOA.City AS OrgCity, 
		VOA.State AS OrgState, 
		VOA.PostalCode AS OrgPostalCode, 
		HDN.ItemRecordID, 
		HDN.ItemBarcode, 
		HDN.BrowseTitle, 
		HDN.BrowseAuthor, 
		HDN.ItemCallNumber, 
		HDN.Price, 
		HDN.Name AS ItemBranchName, 
		HDN.Abbreviation AS ItemBranchAbbreviation, 
		HDN.PhoneNumberOne AS ItemBranchPhone,
		HDN.PickupOrganizationID,
		HDN.HoldPickupAreaID
	FROM 
		Patrons P WITH (NOLOCK)
		INNER JOIN PatronRegistration PR WITH (NOLOCK) 
			ON P.PatronID = PR.PatronID
		INNER JOIN Organizations ORG WITH (NOLOCK) 
			ON P.OrganizationID = ORG.OrganizationID
		INNER JOIN SA_ContactPersons CP WITH (NOLOCK) 
			ON CP.SA_ContactPersonID = ORG.SA_ContactPersonID
		INNER JOIN SA_AddressView VOA WITH (NOLOCK) 
			ON ORG.OrganizationID = VOA.OrganizationID
		INNER JOIN ViewPatronAddresses VPA WITH (NOLOCK) 
			ON P.PatronID = VPA.PatronID
		INNER JOIN Results.Polaris.HoldNotices HDN WITH (NOLOCK)
			ON P.PatronID = HDN.PatronID
		LEFT OUTER JOIN Polaris.NameTitles NT WITH (NOLOCK)
			ON PR.NameTitleID = NT.NameTitleID
	WHERE 
		VPA.AddressTypeID = 2 AND
		VOA.Mnemonic = N'PSPROFMAILADDR'