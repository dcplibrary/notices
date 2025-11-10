
CREATE VIEW Polaris.ML_V1_Holds
AS
-- POL-7342 add activationDate to view

	SELECT
		shr.SysHoldRequestID AS HoldId,
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
		h.activationDate
	FROM
		Polaris.SysHoldRequests AS shr WITH (NOLOCK)
		CROSS APPLY Polaris.ML_V1_Hold(shr.SysHoldRequestID) AS h
