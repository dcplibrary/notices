
CREATE PROCEDURE Polaris.Rpt_ExportNotices 
/**********************************************************************************/
/*	This sp is called by ExportNotices.exe to return notice rows for exporting.	  */
/*  Note:                                                                         */
/*  DeliveryOptionID is only ever set to 1 or 3 in Export Notices                 */
/**********************************************************************************/
	@nReportingOrgID int = 0,
	@nDeliveryOptionID int = 1,
	@nNotificationTypeID int = 1
AS
BEGIN
	SET NOCOUNT ON;

	--Enhanced profile is only available for Phone and Txt notices
	--If SA is set to 'Use enhanced profile' then call that stored procedure and return	
	IF (@nDeliveryOptionID IN (3,4,5,8) AND (SELECT Result FROM Polaris.SA_GetDataByOrg (N'NSPARMRM_USEENHANCEDPROFILE', @nReportingOrgID)) = N'Yes')
	BEGIN
		EXEC Polaris.Rpt_ExportNotices_EnhancedProfile @nReportingOrgID, @nDeliveryOptionID, @nNotificationTypeID
		RETURN;
	END

	IF (@nNotificationTypeID IN (2,18))
	BEGIN
		-- populate Hold notices only if export is enabled
		EXEC Polaris.GenerateHoldNotices @nDeliveryOptionID
	END

	DECLARE @tblOrganizations TABLE
	(
		OrganizationID int not null,
		ParentOrganizationID int not null
	)

	IF ((SELECT OrganizationCodeID FROM Polaris.Organizations WITH (NOLOCK) WHERE OrganizationID = @nReportingOrgID) = 2)
	BEGIN
		INSERT INTO @tblOrganizations (OrganizationID, ParentOrganizationID)
		SELECT OrganizationID, ParentOrganizationID FROM Polaris.Organizations WITH (NOLOCK) 
		WHERE OrganizationID = @nReportingOrgID OR ParentOrganizationID = @nReportingOrgID
	END
	ELSE IF ((SELECT OrganizationCodeID FROM Polaris.Organizations WITH (NOLOCK) WHERE OrganizationID = @nReportingOrgID) = 3)
	BEGIN
		INSERT INTO @tblOrganizations (OrganizationID, ParentOrganizationID)
		SELECT OrganizationID, ParentOrganizationID FROM Polaris.Organizations WITH (NOLOCK) 
		WHERE OrganizationID = @nReportingOrgID
	END
	ELSE
	BEGIN
		INSERT INTO @tblOrganizations (OrganizationID, ParentOrganizationID)
		SELECT OrganizationID, ParentOrganizationID FROM Polaris.Organizations WITH (NOLOCK) 
		WHERE OrganizationCodeID = 3
	END
	
	--
	--	Create #patrons to store Patron data
	--
	CREATE TABLE #patrons
	(
		PatronID INT NOT NULL, 
		NameLast NVARCHAR(100) NULL,
		NameFirst NVARCHAR(32) NULL,
		NameMiddle NVARCHAR(32) NULL,
		NameTitle NVARCHAR(8) NULL,
		NameSuffix NVARCHAR(4) NULL,
		PatronBarcode NVARCHAR(20) NULL,
		PatronCode NVARCHAR(80) NULL,
		RegisteredBranch NVARCHAR(50) NULL,
		RegisteredBranchAbbr NVARCHAR(15) NULL,
		DeliveryOptionID INT NULL,
		PhoneVoice1 NVARCHAR (20) NULL, 
		PhoneVoice2 NVARCHAR (20) NULL, 
		PhoneVoice3 NVARCHAR (20) NULL,
		TxtPhoneNumber INT NULL,
		EmailAddress NVARCHAR (64) NULL, 
		AltEmailAddress NVARCHAR (64) NULL, 
		AddressID int NULL,
		StreetOne NVARCHAR(64) NULL,
		StreetTwo NVARCHAR(64) NULL,
		StreetThree NVARCHAR(64) NULL,
		City NVARCHAR(32) NULL,
		State NVARCHAR(32) NULL,
		Zip NVARCHAR(17) NULL,
		County NVARCHAR(32) NULL,
		AccountBalance money NULL,
		LastActivityDate datetime NULL,
		LanguageID int NULL,
		LanguageDesc NVARCHAR(80),		
		LegalNameLast NVARCHAR(32) NULL,
		LegalNameFirst NVARCHAR(32) NULL,
		LegalNameMiddle NVARCHAR(32) NULL,
		UseLegalNameOnNotices bit null
	)
	
	CREATE INDEX ind_#patrons_PatronID on #patrons(PatronID)
	CREATE INDEX ind_#patrons_namelast on #patrons(NameLast)
	CREATE INDEX ind_#patrons_namefirst on #patrons(NameFirst)
	CREATE INDEX ind_#patrons_namemiddle on #patrons(NameMiddle)
	CREATE INDEX ind_#patrons_legalnamelast on #patrons(LegalNameLast)
	CREATE INDEX ind_#patrons_legalnamefirst on #patrons(LegalNameFirst)
	CREATE INDEX ind_#patrons_legalnamemiddle on #patrons(LegalNameMiddle)
	

	if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (1, 12, 13))	-- printed overdues
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT
			DISTINCT ovd.PatronID
		FROM
			Results.Polaris.OverdueNotices ovd WITH (NOLOCK)
			inner join @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
		WHERE
			ovd.NotificationTypeID IN (1, 12, 13)
			and ovd.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID IN (3, 4, 5) AND @nNotificationTypeID IN (1, 12, 13))	-- phone overdues
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT
			DISTINCT ovd.PatronID
		FROM
			Results.Polaris.NotificationQueue ovd WITH (NOLOCK)
			inner join @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
		WHERE
			ovd.NotificationTypeID IN (1, 12, 13)
			and ovd.DeliveryOptionID IN (3, 4, 5)
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID IN (1, 12, 13))	-- TXT overdues
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT
			DISTINCT ovd.PatronID
		FROM
			Results.Polaris.NotificationQueue ovd WITH (NOLOCK)
			inner join @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
		WHERE
			ovd.NotificationTypeID IN (1, 12, 13)
			and ovd.DeliveryOptionID = 8
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 11)	-- printed bills
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT
			DISTINCT ovd.PatronID
		FROM
			Results.Polaris.OverdueNotices ovd WITH (NOLOCK)
			inner join @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
		WHERE
			ovd.NotificationTypeID = @nNotificationTypeID
			and ovd.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 20)	-- printed manual bills
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT
			DISTINCT mb.PatronID
		FROM
			Results.Polaris.ManualBillNotices mb WITH (NOLOCK)
			inner join @tblOrganizations o ON (mb.ReportingOrgID = o.OrganizationID)
		WHERE
			mb.NotificationTypeID = @nNotificationTypeID
			and mb.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (2, 18))	-- printed hold
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT hd.PatronID
		FROM 
			Results.Polaris.HoldNotices hd WITH (NOLOCK)
			inner join @tblOrganizations o ON (hd.PickupOrganizationID = o.OrganizationID)
		WHERE
			hd.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID IN (3, 4, 5) AND @nNotificationTypeID IN (2, 3, 18))	-- phone hold
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT hd.PatronID
		FROM 
			Results.Polaris.NotificationQueue hd WITH (NOLOCK)
			inner join @tblOrganizations o ON (hd.ReportingOrgID = o.OrganizationID)
		WHERE
			hd.DeliveryOptionID IN (3, 4, 5)
			AND hd.NotificationTypeID IN (2, 3, 18)
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID IN (2, 3, 18))	-- TXT hold
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT hd.PatronID
		FROM 
			Results.Polaris.NotificationQueue hd WITH (NOLOCK)
			inner join @tblOrganizations o ON (hd.ReportingOrgID = o.OrganizationID)
		WHERE
			hd.DeliveryOptionID = 8
			AND hd.NotificationTypeID IN (2, 3, 18)
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (3, 8))	-- printed fine or cancellation
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT nq.PatronID
		FROM 
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			inner join @tblOrganizations o ON (nq.ReportingOrgID = o.OrganizationID)
		WHERE
			nq.NotificationTypeID = @nNotificationTypeID
			and nq.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID = 3 AND @nNotificationTypeID in (11, 20))
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT nq.PatronID
		FROM 
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			inner join @tblOrganizations o ON (nq.ReportingOrgID = o.OrganizationID)
		WHERE
			nq.NotificationTypeID IN (11,20)
			and nq.DeliveryOptionID IN (3, 4, 5)
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID IN (11, 20))
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT nq.PatronID
		FROM 
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			inner join @tblOrganizations o ON (nq.ReportingOrgID = o.OrganizationID)
		WHERE
			nq.NotificationTypeID IN (11,20)
			and nq.DeliveryOptionID = 8
	END
	else if (@nDeliveryOptionID = 3 AND @nNotificationTypeID = 8)	-- phone fine or bill
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT nq.PatronID
		FROM 
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			inner join @tblOrganizations o ON (nq.ReportingOrgID = o.OrganizationID)
		WHERE
			nq.NotificationTypeID = @nNotificationTypeID
			and nq.DeliveryOptionID IN (3, 4, 5)
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID = 8)	-- TXT fine, bill or manual bill
	BEGIN
		INSERT INTO #patrons (PatronID)
		SELECT 
			DISTINCT nq.PatronID
		FROM 
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			inner join @tblOrganizations o ON (nq.ReportingOrgID = o.OrganizationID)
		WHERE
			nq.NotificationTypeID = @nNotificationTypeID
			and nq.DeliveryOptionID = 8
	END
	else
	BEGIN
		return -- not support other notice types
	END
	
	-- figure out patron's general info
	UPDATE
		#Patrons
	SET
		PatronBarcode = pat.Barcode,
		NameLast = pr.NameLast,
		NameFirst = pr.NameFirst,
		NameMiddle = pr.NameMiddle,
		NameTitle = nt.[Description],
		NameSuffix = pr.NameSuffix,
		DeliveryOptionID = pr.DeliveryOptionID,
		PhoneVoice1 = pr.PhoneVoice1, 
		PhoneVoice2 = pr.PhoneVoice2, 
		PhoneVoice3 = pr.PhoneVoice3,
		TxtPhoneNumber = pr.TxtPhoneNumber,
		EmailAddress = pr.EmailAddress, 
		AltEmailAddress = pr.AltEmailAddress,
		AddressID = Polaris.fn_PatronNoticeAddressID(p.PatronID),
		LastActivityDate = pat.LastActivityDate, 
		RegisteredBranch = org.Name,
		RegisteredBranchAbbr = org.Abbreviation,
		PatronCode = pc.Description,
		LanguageID = CASE WHEN pr.LanguageID IS NULL THEN 1033 ELSE al.AdminLanguageID END,
		LanguageDesc = CASE WHEN pr.LanguageID IS NULL THEN N'English' ELSE al.EnglishDescription END,
		--POL-8555 Consume setting to use legal name fields for phone notices
		LegalNameLast = pr.LegalNameLast,
		LegalNameFirst = pr.LegalNameFirst,
		LegalNameMiddle = pr.LegalNameMiddle,
		UseLegalNameOnNotices = pr.UseLegalNameOnNotices
	FROM
		#Patrons p
	INNER JOIN Polaris.PatronRegistration pr WITH (NOLOCK)
		ON (p.PatronID = pr.PatronID)
	INNER JOIN Polaris.Patrons pat WITH (NOLOCK)
		ON pr.PatronID = pat.PatronID
	LEFT OUTER JOIN Polaris.PatronCodes pc WITH (NOLOCK) 
		ON pat.PatronCodeID = pc.PatronCodeID
	LEFT OUTER JOIN Polaris.Organizations org WITH (NOLOCK) 
		ON pat.OrganizationID = org.OrganizationID
	LEFT OUTER JOIN Polaris.Languages l WITH (NOLOCK)
		ON pr.LanguageID = l.LanguageID
	LEFT OUTER JOIN Polaris.AdminLanguages al WITH (NOLOCK)
		ON l.AdminLanguageID = al.AdminLanguageID
	LEFT OUTER JOIN Polaris.NameTitles nt WITH (NOLOCK)
		ON pr.NameTitleID = nt.NameTitleID

	-- figure out patron's address
	UPDATE 
		#patrons
	SET
		StreetOne = a.StreetOne,
		StreetTwo = a.StreetTwo,
		StreetThree = a.StreetThree,
		City = pc.City,
		State = pc.State,
		County = pc.County,
		Zip = IsNull(pc.PostalCode, N'') + IsNull(N'-' + a.ZipPlusFour, N'')
	FROM 
		#patrons p 
		INNER JOIN Polaris.Addresses a WITH (NOLOCK) ON (p.AddressID = a.AddressID)
		INNER JOIN Polaris.PostalCodes pc WITH (NOLOCK) ON pc.PostalCodeID = a.PostalCodeID	


	-- figure patron account balance for printed Bill and Fine
	if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (11, 8, 20))
	BEGIN
		UPDATE #patrons
		SET 
			AccountBalance = p.ChargesAmount
		FROM
			Polaris.Patrons p WITH (NOLOCK)
		WHERE
			#patrons.PatronID = p.PatronID
	END
	
	-- figure LastActivityDate for printed Fine
	if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 8)
	BEGIN
		UPDATE 
			#patrons
		SET
			LastActivityDate = p.LastActivityDate
		FROM
			Polaris.Patrons p WITH (NOLOCK)
		WHERE
			#patrons.PatronID = p.PatronID
	END
	
	--
	--	Create #items to store item data that not yet the notices database tables
	--
	CREATE TABLE #items
	(
		PatronID int NOT NULL,
		NotificationTypeID int NOT NULL,		
		ItemRecordID int NULL, 
		MaterialType NVARCHAR(80), 
		BrowseTitle nvarchar(1050),
		BrowseAuthor nvarchar(255),
		CheckOutDate datetime,
		DateHeld datetime NULL,
		DueDate datetime,
		ItemBarcode NVARCHAR(20),
		ReportingOrgID int NOT NULL,
		TxnID int NULL
	)

	CREATE index ind_#items_patronID on #items(patronID)
	CREATE index ind_#items_ItemID on #items(ItemRecordID)
	CREATE index ind_#items_NotificationTypeID on #items(NotificationTypeID)
	CREATE index ind_#items_ReportingOrgID on #items(ReportingOrgID)

	if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (1, 12, 13))	-- printed overdues
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			BrowseAuthor,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(ovd.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			ovd.BrowseAuthor,
			ic.CheckOutDate,
			ovd.DueDate,
			ci.Barcode,
			ovd.ReportingOrgID
		FROM
			Results.Polaris.OverdueNotices ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			INNER JOIN Polaris.ItemCheckOuts ic WITH (NOLOCK) ON (ovd.ItemRecordID = ic.ItemRecordID and ovd.PatronID = ic.PatronID)
			INNER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID IN (1, 12, 13)	-- all types of overdues
			and ovd.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID = 3 AND @nNotificationTypeID IN (1, 12, 13))	-- phone overdues
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(br.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			ic.CheckOutDate,
			ic.DueDate,
			ci.Barcode,
			ovd.ReportingOrgID
		FROM
			Results.Polaris.NotificationQueue ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			INNER JOIN Polaris.ItemCheckOuts ic WITH (NOLOCK) ON (ovd.ItemRecordID = ic.ItemRecordID and ovd.PatronID = ic.PatronID)
			INNER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			INNER JOIN Polaris.BibliographicRecords br WITH (NOLOCK) ON (ci.AssociatedBibRecordID = br.BibliographicRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID IN (1, 12, 13)	-- all types of overdues
			and ovd.DeliveryOptionID IN (3, 4, 5)	-- phone1, phone2, phone3
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID IN (1, 12, 13))	-- TXT overdues
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(br.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			ic.CheckOutDate,
			ic.DueDate,
			ci.Barcode,
			ovd.ReportingOrgID
		FROM
			Results.Polaris.NotificationQueue ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			INNER JOIN Polaris.ItemCheckOuts ic WITH (NOLOCK) ON (ovd.ItemRecordID = ic.ItemRecordID and ovd.PatronID = ic.PatronID)
			INNER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			INNER JOIN Polaris.BibliographicRecords br WITH (NOLOCK) ON (ci.AssociatedBibRecordID = br.BibliographicRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID IN (1, 12, 13)	-- all types of overdues
			and ovd.DeliveryOptionID = 8	-- SMS
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 11)	-- printed bill
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(ovd.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			ic.CheckOutDate,
			ovd.DueDate,
			ci.Barcode,
			ovd.ReportingOrgID
		FROM
			Results.Polaris.OverdueNotices ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			INNER JOIN Polaris.ItemCheckOuts ic WITH (NOLOCK) ON (ovd.ItemRecordID = ic.ItemRecordID and ovd.PatronID = ic.PatronID)
			INNER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID = @nNotificationTypeID
			and ovd.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID = 3 AND @nNotificationTypeID = 11)	-- phone bill
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(ovd.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			ic.CheckOutDate,
			ic.DueDate,
			ci.Barcode,
			ovd.ReportingOrgID
		FROM
			Results.Polaris.OverdueNotices ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			INNER JOIN Polaris.ItemCheckOuts ic WITH (NOLOCK) ON (ovd.ItemRecordID = ic.ItemRecordID and ovd.PatronID = ic.PatronID)
			INNER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID = @nNotificationTypeID
			and ovd.DeliveryOptionID IN (3, 4, 5)
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID = 11)	-- TXT bill
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(ovd.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			ic.CheckOutDate,
			ic.DueDate,
			ci.Barcode,
			ovd.ReportingOrgID
		FROM
			Results.Polaris.OverdueNotices ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			INNER JOIN Polaris.ItemCheckOuts ic WITH (NOLOCK) ON (ovd.ItemRecordID = ic.ItemRecordID and ovd.PatronID = ic.PatronID)
			INNER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID = @nNotificationTypeID
			and ovd.DeliveryOptionID = 8
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 20)	-- printed manual bill
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID,
			TxnID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(ovd.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			NULL,
			NULL,
			ci.Barcode,
			ovd.ReportingOrgID,
			ovd.TxnID
		FROM
			Results.Polaris.ManualBillNotices ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID = @nNotificationTypeID
			and ovd.DeliveryOptionID = @nDeliveryOptionID
	END
	else if (@nDeliveryOptionID = 3 AND @nNotificationTypeID = 20)	-- phone manual bill
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID,
			TxnID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(ovd.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			NULL,
			NULL,
			ci.Barcode,
			ovd.ReportingOrgID,
			ovd.TxnID
		FROM
			Results.Polaris.ManualBillNotices ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID = @nNotificationTypeID
			and ovd.DeliveryOptionID IN (3, 4, 5)
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID = 20)	-- TXT manual bill
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			CheckOutDate,
			DueDate,
			ItemBarcode,
			ReportingOrgID,
			TxnID
		)
		SELECT
			DISTINCT
			ovd.PatronID,
			ovd.NotificationTypeID,
			ovd.ItemRecordID,
			mt.Description,
			IsNull(ovd.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			NULL,
			NULL,
			ci.Barcode,
			ovd.ReportingOrgID,
			ovd.TxnID
		FROM
			Results.Polaris.ManualBillNotices ovd WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (ovd.ReportingOrgID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ovd.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ovd.ItemRecordId = i.ItemRecordId)
		WHERE
			ovd.NotificationTypeID = @nNotificationTypeID
			and ovd.DeliveryOptionID = 8
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (2, 18))	-- printed hold
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			BrowseAuthor,
			DateHeld,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			hn.PatronID,
			2, --hn.NotificationTypeID,
			ci.ItemRecordID,
			mt.Description,
			IsNull(hn.BrowseTitle, N'') + IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			hn.BrowseAuthor,
			-- per CLC, use HoldTillDate or CT: 49834 simulate a holdtilldate for ILL records. (6/14/16: POL-2290: there is now an ILLRequests.HoldTillDate)
			COALESCE(hdr.HoldTillDate, hdr.LastStatusTransitionDate, ill.HoldTillDate, ill.LastStatusTransitionDate), 
			ci.Barcode,
			hn.PickupOrganizationID
		FROM
			Results.Polaris.HoldNotices hn WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (hn.PickupOrganizationID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (hn.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ci.ItemRecordID = i.ItemRecordID)
			LEFT OUTER JOIN Polaris.SysHoldRequests hdr WITH (NOLOCK) ON (hn.ItemRecordID = hdr.TrappingItemRecordID)
			LEFT OUtER JOIN Polaris.ILLRequests ill WITH (NOLOCK) ON (hn.ItemRecordID = ill.ItemRecordID)
		WHERE
			hn.DeliveryOptionID = @nDeliveryOptionID
	END		
	else if (@nDeliveryOptionID = 3 AND @nNotificationTypeID IN (2, 3, 18))	-- phone hold requests and cancellation
	BEGIN
		-- phone hold requests
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			DateHeld,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			hn.PatronID,
			hn.NotificationTypeID,
			ci.ItemRecordID,
			mt.Description,
			COALESCE(br.BrowseTitle, Polaris.Circ_GetSysHoldRequestDisplayTitle(hdr.SysHoldRequestID, 0), ill.BrowseTitle, N'') + 
				IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			-- per CLC, use HoldTillDate or CT: 49834 simulate a holdtilldate for ILL records. (6/14/16: POL-2290: there is now an ILLRequests.HoldTillDate)
			COALESCE(hdr.HoldTillDate, hdr.LastStatusTransitionDate, ill.HoldTillDate, ill.LastStatusTransitionDate), 
			ci.Barcode,
			hn.ReportingOrgID
		FROM
			Results.Polaris.NotificationQueue hn WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (hn.ReportingOrgID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (hn.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.BibliographicRecords br WITH (NOLOCK) ON (ci.AssociatedBibRecordID = br.BibliographicRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ci.ItemRecordID = i.ItemRecordID)
			LEFT OUTER JOIN Polaris.SysHoldRequests hdr WITH (NOLOCK) ON (ci.ItemRecordID = hdr.TrappingItemRecordID)
			LEFT OUtER JOIN Polaris.ILLRequests ill WITH (NOLOCK) ON (hn.ItemRecordID = ill.ItemRecordID)
		WHERE
			hn.DeliveryOptionID IN (3, 4, 5)
			AND hn.NotificationTypeID in (2, 18)

		-- phone cancellation requests
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			DateHeld,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT DISTINCT
			hn.PatronID,
			3, -- cancellation,
			ci.ItemRecordID,
			mt.Description,
			COALESCE(hn.Title, Polaris.Circ_GetSysHoldRequestDisplayTitle(hdr.SysHoldRequestID, 0), ill.BrowseTitle, br.BrowseTitle, N'') + 
				IsNull(N'  ' + i.Designation, N''), 
			COALESCE(hdr.LastStatusTransitionDate, ill.LastStatusTransitionDate, hn.RequestDate),
			ci.Barcode,
			hn.PickupOrganizationID
		FROM
			Results.Polaris.HoldCancellationNotices hn WITH (NOLOCK) 
			INNER JOIN @tblOrganizations o ON (hn.PickupOrganizationID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.SysHoldRequests hdr WITH (NOLOCK) ON (hn.RequestID = hdr.SysHoldRequestID)
			LEFT OUTER JOIN Polaris.ILLRequests ill WITH (NOLOCK) ON (hn.RequestID = -ill.ILLRequestID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ci.ItemRecordID = hdr.TrappingItemRecordID or ill.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.BibliographicRecords br WITH (NOLOCK) ON (ci.AssociatedBibRecordID = br.BibliographicRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ci.ItemRecordID = i.ItemRecordID)
		WHERE
			hn.DeliveryOptionID IN (3, 4, 5)
	END
	else if (@nDeliveryOptionID = 8 AND @nNotificationTypeID IN (2, 3, 18))	-- TXT hold requests and cancellation
	BEGIN
		-- TXT hold requests
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			DateHeld,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			hn.PatronID,
			hn.NotificationTypeID,
			ci.ItemRecordID,
			mt.Description,
			COALESCE(br.BrowseTitle, Polaris.Circ_GetSysHoldRequestDisplayTitle(hdr.SysHoldRequestID, 0), ill.BrowseTitle, N'') + 
				IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 			
			COALESCE(hdr.HoldTillDate, hdr.LastStatusTransitionDate, ill.HoldTillDate, ill.LastStatusTransitionDate), 
			ci.Barcode,
			hn.ReportingOrgID
		FROM
			Results.Polaris.NotificationQueue hn WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (hn.ReportingOrgID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (hn.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.BibliographicRecords br WITH (NOLOCK) ON (ci.AssociatedBibRecordID = br.BibliographicRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ci.ItemRecordID = i.ItemRecordID)
			LEFT OUTER JOIN Polaris.SysHoldRequests hdr WITH (NOLOCK) ON (ci.ItemRecordID = hdr.TrappingItemRecordID)
			LEFT OUtER JOIN Polaris.ILLRequests ill WITH (NOLOCK) ON (hn.ItemRecordID = ill.ItemRecordID)
		WHERE
			hn.DeliveryOptionID = 8
			AND hn.NotificationTypeID in (2, 18)

		-- phone cancellation requests
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			DateHeld,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT DISTINCT
			hn.PatronID,
			3, -- cancellation,
			ci.ItemRecordID,
			mt.Description,
			COALESCE(hn.Title, Polaris.Circ_GetSysHoldRequestDisplayTitle(hdr.SysHoldRequestID, 0), ill.BrowseTitle, br.BrowseTitle, N'') + 
				IsNull(N'  ' + i.Designation, N''), 
			COALESCE(hdr.LastStatusTransitionDate, ill.LastStatusTransitionDate, hn.RequestDate),
			ci.Barcode,
			hn.PickupOrganizationID
		FROM
			Results.Polaris.HoldCancellationNotices hn WITH (NOLOCK) 
			INNER JOIN @tblOrganizations o ON (hn.PickupOrganizationID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.SysHoldRequests hdr WITH (NOLOCK) ON (hn.RequestID = hdr.SysHoldRequestID)
			LEFT OUTER JOIN Polaris.ILLRequests ill WITH (NOLOCK) ON (hn.RequestID = -ill.ILLRequestID)
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) ON (ci.ItemRecordID = hdr.TrappingItemRecordID or ill.ItemRecordID = ci.ItemRecordID)
			LEFT OUTER JOIN Polaris.BibliographicRecords br WITH (NOLOCK) ON (ci.AssociatedBibRecordID = br.BibliographicRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ci.ItemRecordID = i.ItemRecordID)
		WHERE
			hn.DeliveryOptionID = 8
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 3)	-- printed hold cancellation
	BEGIN
		INSERT INTO #items
		(
			PatronID,
			NotificationTypeID,
			ItemRecordID, 
			MaterialType, 
			BrowseTitle,
			BrowseAuthor,
			DateHeld,
			ItemBarcode,
			ReportingOrgID
		)
		SELECT
			DISTINCT
			cn.PatronID,
			3, -- cancellation,
			IsNull(ci.ItemRecordID, 0),
			mt.Description,
			COALESCE(cn.Title, Polaris.Circ_GetSysHoldRequestDisplayTitle(hdr.SysHoldRequestID, 0), ill.BrowseTitle, br.BrowseTitle, N'') + 
				IsNull(N'  ' + i.Designation, N'') as BrowseTitle, 
			COALESCE(Polaris.Circ_GetSysHoldRequestDisplayAuthor(hdr.SysHoldRequestID, 0), ill.BrowseAuthor, br.BrowseAuthor) as BrowseAuthor,
			COALESCE(hdr.LastStatusTransitionDate, ill.LastStatusTransitionDate),
			ci.Barcode,
			cn.PickupOrganizationID
		FROM
			Results.Polaris.HoldCancellationNotices cn WITH (NOLOCK)
			INNER JOIN @tblOrganizations o ON (cn.PickupOrganizationID = o.OrganizationID)
			LEFT OUTER JOIN Polaris.SysHoldRequests hdr WITH (NOLOCK) ON (cn.RequestID = hdr.SysHoldRequestID)
			LEFT OUTER JOIN Polaris.ILLRequests ill WITH (NOLOCK) ON (cn.RequestID = (-ill.ILLRequestID))
			LEFT OUTER JOIN Polaris.CircItemRecords ci WITH (NOLOCK) 
				ON (ci.ItemRecordID = hdr.TrappingItemRecordID OR ci.ItemRecordID = ill.ItemRecordID)
			LEFT OUTER JOIN Polaris.BibliographicRecords br WITH (NOLOCK) 
				ON (hdr.BibliographicRecordID = br.BibliographicRecordID or ill.BibliographicRecordID = br.BibliographicRecordID)
			LEFT OUTER JOIN Polaris.MaterialTypes mt WITH (NOLOCK) ON (ci.MaterialTypeID = mt.MaterialTypeID)
			LEFT OUTER JOIN Polaris.MfhdIssues i WITH (NOLOCK) ON (ci.ItemRecordID = i.ItemRecordID)
		WHERE
			cn.DeliveryOptionID = @nDeliveryOptionID
	END
	
	--
	--	Return data read by reports
	--
	if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (1, 12, 13)) -- printed overdue
	BEGIN
		SELECT
			ovd.ReportingOrgID as OrganizationID,
			o2.Name AS ReportingOrganization,
			p.NameLast, 
			p.NameFirst, 
			p.NameMiddle, 
			p.NameTitle, 
			p.NameSuffix, 
			p.PatronBarcode,
			p.PatronCode,
			p.RegisteredBranch,
			p.PhoneVoice1,
			p.PhoneVoice2,
			p.PhoneVoice3,
			p.EmailAddress, 
			p.AltEmailAddress, 
			p.StreetOne, 
			p.StreetTwo, 
			p.City,
			p.State,
			p.Zip,
			p.County,
			p.LanguageDesc,
			CASE ovd.NotificationTypeID
				WHEN 1 THEN N'Notice #1'
				WHEN 12 THEN N'Notice #2'
				WHEN 13 THEN N'Notice #3'
				ELSE N''
			END AS NoticeType,
			i.BrowseTitle, 
			ovd.BrowseAuthor, 
			i.MaterialType,
			ovd.ItemBarcode, 
			ovd.ItemCallNumber, 
			ovd.DueDate, 
			i.CheckOutDate,
			o.Name AS CheckOutBranch,
			p.StreetThree
		FROM  
			#patrons p
			INNER JOIN #items i ON (p.PatronID = i.PatronID)
			INNER JOIN Results.Polaris.OverdueNotices ovd WITH (NOLOCK) 
				ON (p.PatronID = ovd.PatronID AND i.ItemRecordID = ovd.ItemRecordID AND i.NotificationTypeID = ovd.NotificationTypeID AND i.ReportingOrgID = ovd.ReportingOrgID)
			INNER JOIN Polaris.Organizations o WITH (NOLOCK) ON (ovd.LoaningOrganizationID = o.OrganizationId)
			INNER JOIN Polaris.Organizations o2 WITH (NOLOCK) ON (ovd.ReportingOrgID = o2.OrganizationId)
		ORDER BY 
			p.NameLast, p.NameFirst, p.NameMiddle, NoticeType
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 11) -- printed bill 
	BEGIN
		SELECT
			ovd.ReportingOrgID as OrganizationID,
			o2.Name as ReportingOrganization,
			p.NameLast, 
			p.NameFirst, 
			p.NameMiddle, 
			p.NameTitle, 
			p.NameSuffix, 
			p.PatronBarcode,
			p.PatronCode,
			p.RegisteredBranch,
			p.PhoneVoice1,
			p.PhoneVoice2,
			p.PhoneVoice3,
			p.EmailAddress, 
			p.AltEmailAddress, 
			p.StreetOne, 
			p.StreetTwo, 
			p.City,
			p.State,
			p.Zip,
			p.County,
			p.LanguageDesc,
			p.AccountBalance,
			i.BrowseTitle, 
			ovd.BrowseAuthor, 
			i.MaterialType,
			ovd.ItemBarcode, 
			ovd.ItemCallNumber, 
			ovd.DueDate, 
			i.CheckOutDate,
			o.Name AS CheckOutBranch, 
			ovd.ReplacementCost,	
			ovd.ProcessingCharge,
			ovd.OverdueCharge,
			p.StreetThree
		FROM  
			#patrons p
			INNER JOIN #items i ON (p.PatronID = i.PatronID)
			INNER JOIN Results.Polaris.OverdueNotices ovd WITH (NOLOCK) 
				ON (p.PatronID = ovd.PatronID AND i.ItemRecordID = ovd.ItemRecordID AND i.NotificationTypeID = ovd.NotificationTypeID AND i.ReportingOrgID = ovd.ReportingOrgID)
			INNER JOIN Polaris.Organizations o WITH (NOLOCK) ON (ovd.LoaningOrganizationID = o.OrganizationId)
			INNER JOIN Polaris.Organizations o2 WITH (NOLOCK) ON (ovd.ReportingOrgID = o2.OrganizationId)
		WHERE
			ovd.DeliveryOptionID = @nDeliveryOptionID
		ORDER BY 
			p.NameLast, p.NameFirst, p.NameMiddle
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 20) -- printed manual bill 
	BEGIN
		SELECT
			ovd.ReportingOrgID as OrganizationID,
			o2.Name as ReportingOrganization,
			p.NameLast, 
			p.NameFirst, 
			p.NameMiddle, 
			p.NameTitle, 
			p.NameSuffix, 
			p.PatronBarcode,
			p.PatronCode,
			p.RegisteredBranch,
			p.PhoneVoice1,
			p.PhoneVoice2,
			p.PhoneVoice3,
			p.EmailAddress, 
			p.AltEmailAddress, 
			p.StreetOne, 
			p.StreetTwo, 
			p.City,
			p.State,
			p.Zip,
			p.County,
			p.LanguageDesc,
			p.AccountBalance,
			i.BrowseTitle, 
			ovd.BrowseAuthor, 
			i.MaterialType,
			ovd.ItemBarcode, 
			ovd.ItemCallNumber,
			frc.FeeDescription AS Reason,
			ovd.AddedMessage AS AdditionalInformation,
			o.name AS ChargingBranch,
			ovd.TxnDate,
			--NULL AS DueDate, 
			--i.CheckOutDate,
			--o.Name AS CheckOutBranch, 
			ovd.Amount,
			p.StreetThree		
		FROM  
			#patrons p
			LEFT OUTER JOIN #items i ON (p.PatronID = i.PatronID)
			LEFT OUTER JOIN Results.Polaris.ManualBillNotices ovd WITH (NOLOCK) 
				ON (p.PatronID = ovd.PatronID AND i.TxnID = ovd.TxnID AND i.NotificationTypeID = ovd.NotificationTypeID AND i.ReportingOrgID = ovd.ReportingOrgID)
			LEFT OUTER JOIN Polaris.Organizations o WITH (NOLOCK) ON (ovd.ChargingLibraryID = o.OrganizationId)
			LEFT OUTER JOIN Polaris.FeeReasonCodes frc WITH (NOLOCK) ON (ovd.FeeReasonCodeID = frc.FeeReasonCodeID)
			LEFT OUTER JOIN Polaris.Organizations o2 WITH (NOLOCK) ON (ovd.ReportingOrgID = o2.OrganizationId)
		WHERE
			ovd.DeliveryOptionID = @nDeliveryOptionID
		ORDER BY 
			p.NameLast, p.NameFirst, p.NameMiddle
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID IN (2, 18)) -- printed hold
	BEGIN
		SELECT
			hn.PickupOrganizationID as OrganizationID,
			p.NameLast, 
			p.NameFirst, 
			p.NameMiddle, 
			p.NameTitle, 
			p.NameSuffix, 
			p.PatronBarcode,
			p.PatronCode,
			p.RegisteredBranch,
			p.PhoneVoice1,
			p.PhoneVoice2,
			p.PhoneVoice3,
			p.EmailAddress, 
			p.AltEmailAddress, 
			p.StreetOne, 
			p.StreetTwo, 
			p.City,
			p.State,
			p.Zip,
			p.County,
			p.LanguageDesc,
			i.BrowseTitle, 
			hn.BrowseAuthor, 
			i.MaterialType,
			hn.ItemBarcode, 
			hn.ItemCallNumber, 
			i.DateHeld,
			hn.HoldTillDate,			
			hn.PickupOrganizationID as [PickupBranchID],
			o.Name as [PickupBranchName],
			p.StreetThree
		FROM  
			#patrons p
			INNER JOIN #items i ON (p.PatronID = i.PatronID)
			INNER JOIN Results.Polaris.HoldNotices hn WITH (NOLOCK) 
				ON (p.PatronID = hn.PatronID AND i.ItemRecordID = hn.ItemRecordID AND i.NotificationTypeID = 2 AND i.ReportingOrgID = hn.PickupOrganizationID)
			INNER JOIN Polaris.Organizations o WITH (NOLOCK) ON (hn.PickupOrganizationID = o.OrganizationID)
		WHERE
			hn.DeliveryOptionID = @nDeliveryOptionID
		ORDER BY 
			p.NameLast, p.NameFirst, p.NameMiddle
	END
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 3) -- printed cancellation
	BEGIN
		SELECT
			hn.PickupOrganizationID as OrganizationID,
			p.NameLast, 
			p.NameFirst, 
			p.NameMiddle, 
			p.NameTitle, 
			p.NameSuffix, 
			p.PatronBarcode,
			p.PatronCode,
			p.RegisteredBranch,
			p.PhoneVoice1,
			p.PhoneVoice2,
			p.PhoneVoice3,
			p.EmailAddress, 
			p.AltEmailAddress, 
			p.StreetOne, 
			p.StreetTwo, 
			p.City,
			p.State,
			p.Zip,
			p.County,
			p.LanguageDesc,
			i.BrowseTitle, 
			i.BrowseAuthor, 
			i.MaterialType,
			i.ItemBarcode, 
			hn.RequestDate,
			hn.Reason,
			p.StreetThree
		FROM  
			#patrons p
			INNER JOIN #items i ON (p.PatronID = i.PatronID)
			INNER JOIN Results.Polaris.HoldCancellationNotices hn WITH (NOLOCK) ON (p.PatronID = hn.PatronID AND i.ReportingOrgID = hn.PickupOrganizationID)
		WHERE
			hn.DeliveryOptionID = @nDeliveryOptionID
			AND i.NotificationTypeID = 3
		ORDER BY 
			p.NameLast, p.NameFirst, p.NameMiddle
	END		
	else if (@nDeliveryOptionID = 1 AND @nNotificationTypeID = 8) -- printed fine
	BEGIN
		SELECT
			nq.ReportingOrgID as OrganizationID,
			o2.Name as ReportingOrganization,
			p.NameLast, 
			p.NameFirst, 
			p.NameMiddle, 
			p.NameTitle, 
			p.NameSuffix, 
			p.PatronBarcode,
			p.PatronCode,
			p.RegisteredBranch,
			p.PhoneVoice1,
			p.PhoneVoice2,
			p.PhoneVoice3,
			p.EmailAddress, 
			p.AltEmailAddress, 
			p.StreetOne, 
			p.StreetTwo, 
			p.City,
			p.State,
			p.Zip,
			p.County,
			p.LanguageDesc,
			p.AccountBalance,			
			fr.FeeDescription as [FeeReason],
			nq.Author,
			nq.Title,
			nq.CallNumber,
			nq.Amount,
			p.StreetThree,
			pa.CheckoutDate
		FROM  
			#patrons p
			INNER JOIN Results.Polaris.FineNoticesReport nq WITH (NOLOCK) ON (p.PatronID = nq.PatronID)
			INNER JOIN @tblOrganizations tmp ON (nq.ReportingOrgID = tmp.OrganizationID)
			INNER JOIN Polaris.PatronAccount pa WITH (NOLOCK) ON (nq.PatronID = pa.PatronID AND nq.TxnID = pa.TxnID)
			INNER JOIN Polaris.FeeReasonCodes fr WITH (NOLOCK) ON (pa.FeeReasonCodeID = fr.FeeReasonCodeID)
			INNER JOIN Polaris.Organizations o2 WITH (NOLOCK) ON (nq.ReportingOrgID = o2.OrganizationId)
		WHERE
			nq.DeliveryOptionID = @nDeliveryOptionID
	END	
	else if (@nNotificationTypeID = 8 AND @nDeliveryOptionID IN (3, 4, 5)) -- phone Fine notices
	BEGIN
	
		SELECT DISTINCT
			N'V' AS [MediaType],	-- map to iTiva definition
			CASE p.LanguageID
				WHEN 1033 THEN N'eng'	-- English
				WHEN 1042 THEN N'kor'	-- Korean
				WHEN 1049 THEN N'rus'	-- Russian
				WHEN 1066 THEN N'vie'	-- Vietnamese
				WHEN 1141 THEN N'haw'	-- Hawaiian 
				WHEN 2052 THEN N'cmn'	-- Chinese (Mandarin Chinese)
				WHEN 3082 THEN N'spa'	-- Spanish - Spain (Modern Sort)
				WHEN 3084 THEN N'fra'	-- French
				WHEN 12289 THEN N'ayl'	-- Arabic (Libyan Arabic)
				ELSE N'eng'	-- doesn't support other languages in Polaris
			END AS [LanguageID], -- map to iTiva defined languageID
			4 AS [NoticeType],	-- map to iTiva defined notification type IDs
			1 AS [NotificationLevel],
			p.PatronBarcode AS [PatronNumber],
			p.NameTitle AS [PatronTitle],

			--POL-8555 Consume setting to use legal name fields for phone notices
			CASE p.UseLegalNameOnNotices
			WHEN 1 THEN p.LegalNameFirst
			WHEN 0 THEN p.NameFirst
			ELSE  p.NameFirst
			END AS [PatronFirstName],
			CASE p.UseLegalNameOnNotices
			WHEN 1 THEN p.LegalNameLast
			WHEN 0 THEN p.NameLast
			ELSE  p.NameLast
			END AS [PatronLastName],

			CASE p.DeliveryOptionID 
				WHEN 3 THEN p.PhoneVoice1
				WHEN 4 THEN p.PhoneVoice2
				WHEN 5 THEN p.PhoneVoice3
				ELSE p.PhoneVoice1
			END AS [PhoneNumber],
			p.EmailAddress, 
			Lib.Abbreviation AS [LibraryCode],
			LEFT(o.Abbreviation, 12) AS [SiteCode], -- conform iTiva size limit
			o.Name AS [SiteName],	
			null as ItemBarcode, 
			null as DueDate, 
			null as BrowseTitle
		FROM  
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			INNER JOIN @tblOrganizations tmp ON (nq.ReportingOrgID = tmp.OrganizationID)
			INNER JOIN Polaris.Organizations lib WITH (NOLOCK) ON (tmp.ParentOrganizationID = lib.OrganizationID)
			INNER JOIN Polaris.Organizations o WITH (NOLOCK) ON (tmp.OrganizationID = o.OrganizationID)
			INNER JOIN #patrons p ON (nq.PatronID = p.PatronID AND nq.DeliveryOptionID IN (3, 4, 5))
			INNER JOIN Polaris.VegaNotificationMatrix vnm WITH (NOLOCK) ON (nq.ReportingOrgID = vnm.OrganizationID AND nq.DeliveryOptionID = vnm.DeliveryOptionID AND nq.NotificationTypeID = vnm.NotificationTypeID)
		WHERE
			vnm.Enabled = 0
			AND nq.NotificationTypeID = @nNotificationTypeID
	END
	else if (@nDeliveryOptionID IN (3, 4, 5)) -- other phone notices
	BEGIN
	
		SELECT DISTINCT
			N'V' AS [MediaType],	-- map to iTiva definition
			CASE p.LanguageID
				WHEN 1033 THEN N'eng'	-- English
				WHEN 1042 THEN N'kor'	-- Korean
				WHEN 1049 THEN N'rus'	-- Russian
				WHEN 1066 THEN N'vie'	-- Vietnamese
				WHEN 1141 THEN N'haw'	-- Hawaiian 
				WHEN 2052 THEN N'cmn'	-- Chinese (Mandarin Chinese)
				WHEN 3082 THEN N'spa'	-- Spanish - Spain (Modern Sort)
				WHEN 3084 THEN N'fra'	-- French
				WHEN 12289 THEN N'ayl'	-- Arabic (Libyan Arabic)
				ELSE N'eng'	-- doesn't support other languages in Polaris
			END AS [LanguageID], -- map to iTiva defined languageID
			CASE i.NotificationTypeID
				WHEN 1 THEN 1
				WHEN 12 THEN 1
				WHEN 13 THEN 1
				WHEN 2 THEN 2
				WHEN 18 THEN 2
				WHEN 3 THEN 3
				WHEN 8 THEN 4
				WHEN 11 THEN 4
				ELSE 1
			END AS [NoticeType],	-- map to iTiva defined notification type IDs
			CASE i.NotificationTypeID
				WHEN 1 THEN 1
				WHEN 12 THEN 2
				WHEN 13 THEN 3
				ELSE 1
			END AS [NotificationLevel],
			p.PatronBarcode AS [PatronNumber],
			p.NameTitle AS [PatronTitle], 
			
			--POL-8555 Consume setting to use legal name fields for phone notices
			CASE p.UseLegalNameOnNotices
			WHEN 1 THEN p.LegalNameFirst
			WHEN 0 THEN p.NameFirst
			ELSE  p.NameFirst
			END AS [PatronFirstName],
			CASE p.UseLegalNameOnNotices
			WHEN 1 THEN p.LegalNameLast
			WHEN 0 THEN p.NameLast
			ELSE  p.NameLast
			END AS [PatronLastName],

			CASE p.DeliveryOptionID 
				WHEN 3 THEN p.PhoneVoice1
				WHEN 4 THEN p.PhoneVoice2
				WHEN 5 THEN p.PhoneVoice3
				ELSE p.PhoneVoice1
			END AS [PhoneNumber],
			p.EmailAddress, 
			lib.Abbreviation AS [LibraryCode],
			LEFT(o.Abbreviation, 12) AS [SiteCode], -- conform to iTiva size limit
			o.Name AS [SiteName],	
			i.ItemBarcode, 
			CASE i.NotificationTypeID
				WHEN 2 THEN i.DateHeld
				WHEN 3 THEN i.DateHeld
				WHEN 18 THEN i.DateHeld
				ELSE i.DueDate
			END AS [DueDate], 
			i.BrowseTitle
		FROM  
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			INNER JOIN @tblOrganizations tmp ON (nq.ReportingOrgID = tmp.OrganizationID)
			INNER JOIN Polaris.Organizations lib WITH (NOLOCK) ON (tmp.ParentOrganizationID = lib.OrganizationID)
			INNER JOIN Polaris.Organizations o WITH (NOLOCK) ON (tmp.OrganizationID = o.OrganizationID)
			INNER JOIN #patrons p ON (nq.PatronID = p.PatronID AND nq.DeliveryOptionID IN (3, 4, 5))
			INNER JOIN #items i ON (p.PatronID = i.PatronID AND nq.NotificationTypeID = i.NotificationTypeID AND nq.ReportingOrgID = i.ReportingOrgID)
			INNER JOIN Polaris.VegaNotificationMatrix vnm WITH (NOLOCK) ON (nq.ReportingOrgID = vnm.OrganizationID AND nq.DeliveryOptionID = vnm.DeliveryOptionID AND nq.NotificationTypeID = vnm.NotificationTypeID)
		WHERE
			vnm.Enabled = 0
	END
	else if (@nNotificationTypeID = 8 AND @nDeliveryOptionID = 8) -- TXT Fine notices
	BEGIN
		SELECT DISTINCT
			N'T' AS [MediaType],	-- map to iTiva definition
			CASE p.LanguageID
				WHEN 1033 THEN N'eng'	-- English
				WHEN 1042 THEN N'kor'	-- Korean
				WHEN 1049 THEN N'rus'	-- Russian
				WHEN 1066 THEN N'vie'	-- Vietnamese
				WHEN 1141 THEN N'haw'	-- Hawaiian 
				WHEN 2052 THEN N'cmn'	-- Chinese (Mandarin Chinese)
				WHEN 3082 THEN N'spa'	-- Spanish - Spain (Modern Sort)
				WHEN 3084 THEN N'fra'	-- French
				WHEN 12289 THEN N'ayl'	-- Arabic (Libyan Arabic)
				ELSE N'eng'	-- doesn't support other languages in Polaris
			END AS [LanguageID], -- map to iTiva defined languageID
			4 AS [NoticeType],	-- map to iTiva defined notification type IDs
			1 AS [NotificationLevel],
			p.PatronBarcode AS [PatronNumber],
			p.NameTitle AS [PatronTitle], 
			p.NameFirst AS [PatronFirstName], 
			p.NameLast AS [PatronLastName],  
			CASE p.TxtPhoneNumber 
				WHEN 1 THEN p.PhoneVoice1
				WHEN 2 THEN p.PhoneVoice2
				WHEN 3 THEN p.PhoneVoice3
				ELSE p.PhoneVoice1
			END AS [PhoneNumber],
			p.EmailAddress, 
			Lib.Abbreviation AS [LibraryCode],
			LEFT(o.Abbreviation, 12) AS [SiteCode], -- conform iTiva size limit
			o.Name AS [SiteName],	
			null as ItemBarcode, 
			null as DueDate, 
			null as BrowseTitle
		FROM  
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			INNER JOIN @tblOrganizations tmp ON (nq.ReportingOrgID = tmp.OrganizationID)
			INNER JOIN Polaris.Organizations lib WITH (NOLOCK) ON (tmp.ParentOrganizationID = lib.OrganizationID)
			INNER JOIN Polaris.Organizations o WITH (NOLOCK) ON (tmp.OrganizationID = o.OrganizationID)
			INNER JOIN #patrons p ON (nq.PatronID = p.PatronID AND nq.DeliveryOptionID = 8)
			INNER JOIN Polaris.VegaNotificationMatrix vnm WITH (NOLOCK) ON (nq.ReportingOrgID = vnm.OrganizationID AND nq.DeliveryOptionID = vnm.DeliveryOptionID AND nq.NotificationTypeID = vnm.NotificationTypeID)
		WHERE
			vnm.Enabled = 0
			AND nq.NotificationTypeID = @nNotificationTypeID
	END
	else if (@nDeliveryOptionID = 8) -- other phone notices
	BEGIN
		SELECT DISTINCT
			N'T' AS [MediaType],	-- map to iTiva definition
			CASE p.LanguageID
				WHEN 1033 THEN N'eng'	-- English
				WHEN 1042 THEN N'kor'	-- Korean
				WHEN 1049 THEN N'rus'	-- Russian
				WHEN 1066 THEN N'vie'	-- Vietnamese
				WHEN 1141 THEN N'haw'	-- Hawaiian 
				WHEN 2052 THEN N'cmn'	-- Chinese (Mandarin Chinese)
				WHEN 3082 THEN N'spa'	-- Spanish - Spain (Modern Sort)
				WHEN 3084 THEN N'fra'	-- French
				WHEN 12289 THEN N'ayl'	-- Arabic (Libyan Arabic)
				ELSE N'eng'	-- doesn't support other languages in Polaris
			END AS [LanguageID], -- map to iTiva defined languageID
			CASE i.NotificationTypeID
				WHEN 1 THEN 1
				WHEN 12 THEN 1
				WHEN 13 THEN 1
				WHEN 2 THEN 2
				WHEN 18 THEN 2
				WHEN 3 THEN 3
				WHEN 8 THEN 4
				WHEN 11 THEN 4
				WHEN 20 THEN 4
				ELSE 1
			END AS [NoticeType],	-- map to iTiva defined notification type IDs
			CASE i.NotificationTypeID
				WHEN 1 THEN 1
				WHEN 12 THEN 2
				WHEN 13 THEN 3
				ELSE 1
			END AS [NotificationLevel],
			p.PatronBarcode AS [PatronNumber],
			p.NameTitle AS [PatronTitle], 
			p.NameFirst AS [PatronFirstName], 
			p.NameLast AS [PatronLastName],  
			CASE p.TxtPhoneNumber 
				WHEN 1 THEN p.PhoneVoice1
				WHEN 2 THEN p.PhoneVoice2
				WHEN 3 THEN p.PhoneVoice3
				ELSE p.PhoneVoice1
			END AS [PhoneNumber],
			p.EmailAddress, 
			lib.Abbreviation AS [LibraryCode],
			LEFT(o.Abbreviation, 12) AS [SiteCode], -- conform to iTiva size limit
			o.Name AS [SiteName],	
			i.ItemBarcode, 
			CASE i.NotificationTypeID
				WHEN 2 THEN i.DateHeld
				WHEN 3 THEN i.DateHeld
				WHEN 18 THEN i.DateHeld
				ELSE i.DueDate
			END AS [DueDate], 
			i.BrowseTitle
		FROM  
			Results.Polaris.NotificationQueue nq WITH (NOLOCK)
			INNER JOIN @tblOrganizations tmp ON (nq.ReportingOrgID = tmp.OrganizationID)
			INNER JOIN Polaris.Organizations lib WITH (NOLOCK) ON (tmp.ParentOrganizationID = lib.OrganizationID)
			INNER JOIN Polaris.Organizations o WITH (NOLOCK) ON (tmp.OrganizationID = o.OrganizationID)
			INNER JOIN #patrons p ON (nq.PatronID = p.PatronID AND nq.DeliveryOptionID = 8)
			INNER JOIN #items i ON (p.PatronID = i.PatronID AND nq.NotificationTypeID = i.NotificationTypeID AND nq.ReportingOrgID = i.ReportingOrgID)
			INNER JOIN Polaris.VegaNotificationMatrix vnm WITH (NOLOCK) ON (nq.ReportingOrgID = vnm.OrganizationID AND nq.DeliveryOptionID = vnm.DeliveryOptionID AND nq.NotificationTypeID = vnm.NotificationTypeID)
		WHERE
			vnm.Enabled = 0
	END
	
	DROP TABLE #items
	DROP TABLE #Patrons
END
