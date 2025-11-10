CREATE VIEW Polaris.DeliveryOptions
AS
	SELECT 
		DeliveryOptionID, 
		DeliveryOption
	FROM Polaris.SA_DeliveryOptions (NOLOCK) 
	WHERE 
		Enabled = 1