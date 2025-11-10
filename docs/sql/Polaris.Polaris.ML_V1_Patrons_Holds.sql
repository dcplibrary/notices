
CREATE VIEW Polaris.ML_V1_Patrons_Holds
AS
-- POL-7341 add activationDate to view

	SELECT
		p.PatronID AS PatronId,
		h.SortTitle,
		h.record,
		h.recordType,
		h.author,
		h.ISBN,
		h.title,
		h.materialTypeCode,
		h.parentRecord,
		h.parentRecordType,
		h.id,
		h.frozen,
		h.placedDate,
		h.pickupLocation,
		h.statusName,
		h.statusCode,
		h.[priority],
		h.priorityQueueLength,
		cast(0 as bit) as isILLRequest,
		CAST(CASE WHEN ((h.statusCode in (1, 3, 4, 18))
			OR (h.statusCode = 5 /* Shipped */ AND ISNULL(pppp.Value, N'No') = N'Yes')
			OR (h.statusCode = 6 /* Held */ AND ISNULL(pppp2.Value, N'No') = N'Yes')) THEN 1
		ELSE 0 
		END AS BIT) AS canCancel,
		CAST(CASE WHEN h.statusCode in (1, 3, 4, 5, 6, 18) THEN (SELECT ISNULL ( (SELECT CASE WHEN Exclude = 0 THEN 1 ELSE 0 END from Polaris.HoldsPickupStatusesExclude AS hpse WITH (NOLOCK) WHERE hpse.ExcludedStatusID = h.statusCode), 0) )
		ELSE 0
		END AS BIT) AS canChangePickupLocation,
		CAST(CASE WHEN h.statusCode in (1, 3, 4, 18) THEN 1
		ELSE 0 
		END AS BIT) AS canSuspendReactivate,
		h.activationDate
	FROM
		Polaris.Patrons AS p WITH (NOLOCK)
		INNER JOIN Polaris.SysHoldRequests AS shr WITH (NOLOCK) ON
		(
			shr.PatronID = p.PatronID
		)
		LEFT OUTER JOIN polaris.adminattributes as aa with (nolock) ON aa.mnemonic = N'REQPARM_CANCEL_SHIPPED'
		LEFT OUTER JOIN polaris.organizationspppp as pppp with (nolock) ON pppp.attrid = aa.Attrid and pppp.OrganizationID = 1
		LEFT OUTER JOIN polaris.adminattributes as aa2 with (nolock) ON aa2.mnemonic = N'REQPARM_CANCEL_HELD'
		LEFT OUTER JOIN polaris.organizationspppp as pppp2 with (nolock) ON pppp2.attrid = aa2.Attrid and pppp.OrganizationID = 1
		CROSS APPLY Polaris.ML_V1_Hold(shr.SysHoldRequestID) AS h
	UNION
	SELECT
		p.PatronID AS PatronId,
		ih.SortTitle,
		ih.record,
		ih.recordType,
		ih.author,
		ih.ISBN,
		ih.title,
		ih.materialTypeCode,
		ih.parentRecord,
		ih.parentRecordType,
		ih.id,
		ih.frozen,
		ih.placedDate,
		ih.pickupLocation,
		ih.statusName,
		ih.statusCode,
		ih.[priority],
		ih.priorityQueueLength,
		cast(1 as bit) AS isILLRequest,
		cast(0 as bit) AS canCancel,
		cast(0 as bit) AS canChangePickupLocation,
		cast(0 as bit) AS canSuspendReactivate,
		ih.activationDate
	FROM
		Polaris.Patrons AS p WITH (NOLOCK)
		INNER JOIN Polaris.ILLRequests AS illr WITH (NOLOCK) ON
		(
			illr.PatronID = p.PatronID
		)
		CROSS APPLY Polaris.ML_V1_ILL_Hold(illr.ILLRequestID) AS ih
