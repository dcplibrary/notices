USE [Results]
GO

/****** Object:  Table [Polaris].[NotificationQueue]    Script Date: 11/5/2025 7:47:34 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [Polaris].[NotificationQueue](
	[ItemRecordID] [int] NULL,
	[NotificationTypeID] [int] NOT NULL,
	[PatronID] [int] NOT NULL,
	[DeliveryOptionID] [int] NOT NULL,
	[Processed] [bit] NOT NULL,
	[MinorPatronID] [int] NULL,
	[ReportingOrgID] [int] NULL,
	[Amount] [money] NULL,
	[CreationDate] [datetime] NULL,
	[IsAdditionalTxt] [bit] NULL,
	[NotificationQueueID] [int] IDENTITY(1,1) NOT NULL,
 CONSTRAINT [pk_NotificationQueue] PRIMARY KEY CLUSTERED 
(
	[NotificationQueueID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO

ALTER TABLE [Polaris].[NotificationQueue] ADD  CONSTRAINT [DF_NotificationQueue_Processed]  DEFAULT ((0)) FOR [Processed]
GO

ALTER TABLE [Polaris].[NotificationQueue] ADD  CONSTRAINT [DF_NotificationQueue_CreationDate]  DEFAULT (getdate()) FOR [CreationDate]
GO

ALTER TABLE [Polaris].[NotificationQueue] ADD  CONSTRAINT [DF_NotificationQueue_IsAdditionalTxt]  DEFAULT ((0)) FOR [IsAdditionalTxt]
GO

