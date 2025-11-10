USE [Results]
GO

/****** Object:  Table [Polaris].[HoldNotices]    Script Date: 11/5/2025 7:55:19 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [Polaris].[HoldNotices](
	[ItemRecordID] [int] NOT NULL,
	[AssignedBranchID] [int] NOT NULL,
	[PickupOrganizationID] [int] NOT NULL,
	[PatronID] [int] NOT NULL,
	[ItemBarcode] [nvarchar](20) NOT NULL,
	[BrowseTitle] [nvarchar](255) NULL,
	[BrowseAuthor] [nvarchar](255) NULL,
	[ItemCallNumber] [nvarchar](370) NULL,
	[Price] [money] NULL,
	[Abbreviation] [nvarchar](15) NULL,
	[Name] [nvarchar](50) NULL,
	[PhoneNumberOne] [nvarchar](20) NULL,
	[DeliveryOptionID] [int] NULL,
	[HoldTillDate] [datetime] NULL,
	[ItemFormatID] [int] NULL,
	[AdminLanguageID] [int] NULL,
	[NotificationTypeID] [int] NULL,
	[HoldPickupAreaID] [int] NULL
) ON [PRIMARY]
GO

ALTER TABLE [Polaris].[HoldNotices] ADD  CONSTRAINT [DF_HoldNotices_DeliveryOptionID]  DEFAULT ((1)) FOR [DeliveryOptionID]
GO

ALTER TABLE [Polaris].[HoldNotices] ADD  CONSTRAINT [DF_HoldNotices_HoldPickupAreaID]  DEFAULT ((0)) FOR [HoldPickupAreaID]
GO

EXEC sys.sp_addextendedproperty @name=N'Desc', @value=N'Hold pickup area identifier' , @level0type=N'SCHEMA',@level0name=N'Polaris', @level1type=N'TABLE',@level1name=N'HoldNotices', @level2type=N'COLUMN',@level2name=N'HoldPickupAreaID'
GO

