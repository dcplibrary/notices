SET NOCOUNT ON
SELECT [PatronId]
      ,[ItemRecordId]
      ,[TxnId]
      ,[NotificationTypeId]
      ,[ReportingOrgId]
      ,[DeliveryOptionId]
      ,[NoticeDate]
      ,[Amount]
      ,[NotificationStatusId]
      ,[Title]
  FROM [Results].[Polaris].[NotificationHistory]
  WHERE NoticeDate >= DATEADD(day, -6, GETDATE())
  ORDER BY NoticeDate DESC